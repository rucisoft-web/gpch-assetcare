<?php

namespace App\Services;

use App\Models\ApprovalLimit;
use App\Models\Asset;
use App\Models\RoomBlock;
use App\Models\StatusEvent;
use App\Models\User;
use App\Models\WoCostLine;
use App\Models\WorkOrder;
use App\Models\WoTimeLog;
use Illuminate\Support\Facades\DB;

/**
 * Status terkontrol work order (bagian 8):
 * draft → awaiting_approval → approved → assigned → in_progress → completed → verified → closed,
 * on_hold / cancelled / reopened hanya lewat aksi bernama, beserta alasan & riwayat.
 * Setiap aksi: transaksi + row lock + pemeriksaan versi (409) + field wajib (422) + izin (403).
 */
class WorkOrderService
{
    /** aksi => [status asal yang diizinkan, status tujuan, izin] */
    public const ACTIONS = [
        'submit' => [['draft', 'reopened'], 'awaiting_approval', 'wo.assign'],
        'approve_direct' => [['draft'], 'approved', 'wo.assign'],
        'approve' => [['awaiting_approval'], 'approved', 'wo.approve'],
        'return_draft' => [['awaiting_approval'], 'draft', 'wo.approve'],
        'assign' => [['approved', 'assigned', 'reopened'], 'assigned', 'wo.assign'],
        'start' => [['assigned', 'reopened'], 'in_progress', 'wo.execute'],
        'hold' => [['assigned', 'in_progress'], 'on_hold', 'wo.execute'],
        'resume' => [['on_hold'], 'in_progress', 'wo.execute'],
        'complete' => [['in_progress'], 'completed', 'wo.execute'],
        'verify' => [['completed'], 'verified', 'wo.verify'],
        'reject_back' => [['completed'], 'in_progress', 'wo.verify'],
        'close' => [['verified'], 'closed', 'wo.close'],
        'reopen' => [['closed', 'verified'], 'reopened', 'wo.close'],
        'cancel' => [['draft', 'awaiting_approval', 'approved', 'assigned', 'on_hold', 'reopened'], 'cancelled', 'wo.assign'],
    ];

    public const ACTION_LABELS = [
        'submit' => 'Ajukan persetujuan', 'approve_direct' => 'Setujui langsung (tanpa biaya)', 'approve' => 'Setujui',
        'return_draft' => 'Kembalikan ke draft', 'assign' => 'Tugaskan', 'start' => 'Mulai kerja (check-in)', 'hold' => 'Tunda (hold)',
        'resume' => 'Lanjutkan', 'complete' => 'Selesai', 'verify' => 'Terima hasil (verifikasi)', 'reject_back' => 'Kembalikan ke teknisi',
        'close' => 'Tutup work order', 'reopen' => 'Buka ulang', 'cancel' => 'Batalkan',
    ];

    public function __construct(protected SlaService $sla, protected NotificationService $notify) {}

    public function can(WorkOrder $wo, string $action, ?User $user): bool
    {
        [$from, , $perm] = self::ACTIONS[$action];
        if (! $user || ! in_array($wo->status, $from, true) || ! $user->hasPermission($perm)) {
            return false;
        }
        // teknisi hanya pada tugasnya; pemegang wo.assign boleh mewakili
        if (in_array($action, ['start', 'hold', 'resume', 'complete'], true) && ! $user->hasPermission('wo.assign')) {
            return $this->isAssignee($wo, $user);
        }
        if ($action === 'approve_direct') {
            return ! $wo->requires_approval && ((float) $wo->estimated_cost <= 0 || $wo->is_emergency);
        }

        return true;
    }

    public function isAssignee(WorkOrder $wo, User $user): bool
    {
        return (int) $wo->pic_id === (int) $user->id || in_array((string) $user->id, array_map('strval', $wo->team_ids ?? []), true);
    }

    public function run(WorkOrder $wo, string $action, array $data, User $user, ?int $expectedVersion = null): WorkOrder
    {
        if (! isset(self::ACTIONS[$action])) {
            throw WorkflowException::invalid('Aksi tidak dikenal.');
        }

        $result = DB::transaction(function () use ($wo, $action, $data, $user, $expectedVersion) {
            /** @var WorkOrder $wo */
            $wo = WorkOrder::whereKey($wo->id)->lockForUpdate()->firstOrFail();
            if ($expectedVersion !== null && (int) $wo->version !== $expectedVersion) {
                throw WorkflowException::conflict();
            }
            [$fromAllowed, $to] = self::ACTIONS[$action];
            if (! in_array($wo->status, $fromAllowed, true)) {
                throw WorkflowException::invalid('Aksi "'.self::ACTION_LABELS[$action].'" tidak berlaku untuk status '.WorkOrder::STATUSES[$wo->status].'.');
            }
            if (! $this->can($wo, $action, $user)) {
                throw WorkflowException::forbidden();
            }
            $from = $wo->status;
            $reason = $data['reason'] ?? null;
            $extra = [];
            $update = $this->{'do'.str_replace('_', '', ucwords($action, '_'))}($wo, $data, $user, $extra);
            $update['status'] = $to;
            $update['version'] = $wo->version + 1;
            $wo->auditReason = $reason;
            $wo->update($update);
            $this->event($wo, $from, $to, $action, $reason, $extra);
            app(ServiceRequestService::class)->syncFromWorkOrder($wo, $to);

            return $wo;
        });
        $this->afterAction($result, $action, $user);

        return $result->refresh();
    }

    // ---------------------------------------------------------------- aksi
    protected function doSubmit(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        $cost = $d['estimated_cost'] ?? $wo->estimated_cost;

        return ['requires_approval' => true, 'estimated_cost' => $cost, 'approval_note' => $d['reason'] ?? null];
    }

    protected function doApproveDirect(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        if ($wo->is_emergency && (float) $wo->estimated_cost > 0) {
            if (blank($d['reason'] ?? null)) {
                throw WorkflowException::invalid('Jalur darurat wajib disertai alasan; persetujuan susulan akan diminta.');
            }
            $x['emergency_bypass'] = true;
        }

        return ['approved_by' => $u->id, 'approved_at' => now()];
    }

    protected function doApprove(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        if ((int) $wo->created_by === (int) $u->id) {
            throw WorkflowException::forbidden('Pembuat work order tidak dapat menyetujui pengajuannya sendiri.');
        }
        $cost = (float) $wo->estimated_cost;
        if ($cost > 0 && ! $this->hasApprovalAuthority($u, $cost, $wo->property_id)) {
            throw WorkflowException::forbidden('Belum ada jalur persetujuan yang sah untuk nilai Rp '.number_format($cost, 0, ',', '.').' (Pengaturan › Batas persetujuan).');
        }
        $x['amount'] = $cost;

        return ['approved_by' => $u->id, 'approved_at' => now(), 'approval_note' => $d['reason'] ?? null];
    }

    protected function doReturnDraft(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        $this->requireReason($d);

        return ['requires_approval' => true];
    }

    protected function doAssign(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        $pic = User::find($d['pic_id'] ?? null);
        if (! $pic || ! $pic->hasPermission('wo.execute')) {
            throw WorkflowException::invalid('Pilih PIC yang memiliki peran teknisi.');
        }
        $permits = array_values($d['permits'] ?? $wo->permits ?? []);
        $team = array_values(array_filter(array_map('intval', $d['team_ids'] ?? []), fn ($id) => $id !== (int) $pic->id));
        if (array_intersect($permits, ['hot_work', 'height', 'loto']) && $team === []) {
            throw WorkflowException::invalid('Pekerjaan khusus (hot work, ketinggian, LOTO) tidak boleh ditugaskan kepada satu petugas saja.');
        }
        if ($wo->asset && $wo->asset->warrantyActive() && blank($d['warranty_decision'] ?? $wo->warranty_decision)) {
            throw WorkflowException::invalid('Aset masih bergaransi sampai '.$wo->asset->warranty_until->format('d/m/Y').'. Tentukan keputusan klaim garansi atau pengecualian.');
        }
        $x['pic'] = $pic->name;

        return [
            'pic_id' => $pic->id, 'team_ids' => $team, 'scheduled_at' => $d['scheduled_at'] ?? now(),
            'estimated_hours' => $d['estimated_hours'] ?? $wo->estimated_hours, 'permits' => $permits,
            'room_access_needed' => in_array('room_access', $permits, true) || $wo->room_access_needed,
            'needs_vendor' => (bool) ($d['needs_vendor'] ?? $wo->needs_vendor), 'vendor_id' => $d['vendor_id'] ?? $wo->vendor_id,
            'warranty_decision' => $d['warranty_decision'] ?? $wo->warranty_decision,
        ];
    }

    protected function doStart(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        WoTimeLog::create(['work_order_id' => $wo->id, 'technician_id' => $u->id, 'started_at' => now(), 'rate_snapshot' => $u->labor_rate]);
        if ($wo->asset && ($d['set_under_repair'] ?? true) && $wo->asset->operational_status !== 'under_repair') {
            $this->setAssetStatus($wo->asset, 'under_repair', 'Mulai '.$wo->number, $wo);
        }

        return ['started_at' => $wo->started_at ?? now()];
    }

    protected function doHold(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        $this->requireReason($d);
        $this->stopTimers($wo);

        return ['hold_started_at' => now(), 'hold_reason' => $d['reason']];
    }

    protected function doResume(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        $paused = $wo->hold_started_at ? (int) $wo->hold_started_at->diffInMinutes(now()) : 0;
        $x['paused_minutes'] = $paused;
        WoTimeLog::create(['work_order_id' => $wo->id, 'technician_id' => $u->id, 'started_at' => now(), 'rate_snapshot' => $u->labor_rate]);

        return ['hold_minutes' => $wo->hold_minutes + $paused, 'hold_started_at' => null, 'hold_reason' => null];
    }

    protected function doComplete(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        foreach (['diagnosis' => 'diagnosis', 'action_taken' => 'tindakan', 'function_test_result' => 'hasil uji fungsi'] as $f => $label) {
            if (blank($d[$f] ?? $wo->{$f})) {
                throw WorkflowException::invalid('Lengkapi '.$label.' sebelum menyatakan selesai.');
            }
        }
        $wo->loadMissing('costLines', 'attachments', 'asset');
        $noCost = $d['no_cost_reason'] ?? $wo->no_cost_reason;
        if ($wo->costLines->isEmpty() && blank($noCost)) {
            throw WorkflowException::invalid('Isi biaya (material/jasa/tenaga) atau alasan belum ada biaya.');
        }
        $exception = $d['evidence_exception_reason'] ?? $wo->evidence_exception_reason;
        $pmChecklist = $wo->pm_checklist;
        if ($pmChecklist) {
            if (array_key_exists('pm_done', $d)) {
                $done = array_map('intval', (array) $d['pm_done']);
                foreach ($pmChecklist as $i => $row) {
                    $pmChecklist[$i]['done'] = in_array($i, $done, true);
                }
            }
            if (collect($pmChecklist)->contains(fn ($r) => empty($r['done'])) && blank($exception) && blank($d['follow_up'] ?? $wo->follow_up)) {
                throw WorkflowException::invalid('Checklist PM belum lengkap: centang semua butir, atau isi tindak lanjut / alasan pengecualian untuk butir yang tidak dikerjakan.');
            }
        }
        if ($wo->isCritical()) {
            $permits = $wo->permits ?? [];
            $checked = array_keys(array_filter($d['checklist'] ?? $wo->checklist ?? []));
            if (array_diff($permits, $checked) && blank($exception)) {
                throw WorkflowException::invalid('Pekerjaan kritis: semua checklist izin kerja harus dicentang, atau isi alasan pengecualian.');
            }
            if ($wo->photoCount() === 0 && blank($exception)) {
                throw WorkflowException::invalid('Pekerjaan kritis memerlukan bukti foto (unggah di tab Lampiran) atau alasan pengecualian.');
            }
        }
        $this->stopTimers($wo);
        $x['test'] = $d['function_test_result'] ?? $wo->function_test_result;

        return [
            'failure_mode' => $d['failure_mode'] ?? $wo->failure_mode,
            'diagnosis' => $d['diagnosis'] ?? $wo->diagnosis, 'action_taken' => $d['action_taken'] ?? $wo->action_taken,
            'function_test_result' => $d['function_test_result'] ?? $wo->function_test_result,
            'root_cause' => $d['root_cause'] ?? $wo->root_cause, 'follow_up' => $d['follow_up'] ?? $wo->follow_up,
            'checklist' => $d['checklist'] ?? $wo->checklist, 'pm_checklist' => $pmChecklist, 'evidence_exception_reason' => $exception,
            'no_cost_reason' => $noCost, 'restored_at' => $d['restored_at'] ?? now(),
            'completed_at' => now(), 'completed_by' => $u->id,
        ];
    }

    protected function doVerify(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        if ($wo->isCritical() && (int) $wo->completed_by === (int) $u->id) {
            throw WorkflowException::forbidden('Pekerjaan kritis harus diverifikasi oleh pemeriksa selain teknisi pelaksana.');
        }
        $status = $d['asset_status_after'] ?? null;
        if ($wo->asset_id && blank($status)) {
            throw WorkflowException::invalid('Konfirmasi status operasional aset setelah pekerjaan.');
        }
        if ($wo->room_access_needed && ! array_key_exists('fo_confirmed', $d)) {
            throw WorkflowException::invalid('Kamar terdampak: catat konfirmasi Front Office.');
        }
        if ($wo->asset && $status && $wo->asset->operational_status !== $status) {
            $this->setAssetStatus($wo->asset, $status, 'Verifikasi '.$wo->number, $wo);
        }

        return ['verified_by' => $u->id, 'verified_at' => now(), 'verification_note' => $d['reason'] ?? null, 'asset_status_after' => $status, 'fo_confirmed' => $d['fo_confirmed'] ?? null];
    }

    protected function doRejectBack(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        $this->requireReason($d);

        return ['completed_at' => null, 'completed_by' => null];
    }

    protected function doClose(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        if (! $wo->verified_by || blank($wo->diagnosis) || blank($wo->action_taken) || blank($wo->function_test_result)) {
            throw WorkflowException::invalid('Data penutupan belum lengkap (diagnosis, tindakan, uji fungsi, verifikator).');
        }
        $wo->loadMissing('costLines');
        if ($wo->costLines->isEmpty() && blank($wo->no_cost_reason)) {
            throw WorkflowException::invalid('Biaya atau alasan belum ada biaya wajib diisi.');
        }
        $x['cost_status'] = $wo->costLines->contains('cost_status', 'provisional') ? 'provisional' : 'final';

        return ['closed_at' => now(), 'closed_by' => $u->id, 'cost_status' => $x['cost_status']];
    }

    protected function doReopen(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        $this->requireReason($d);
        // histori kejadian pertama dipertahankan dalam status_events; SLA siklus baru terpisah
        $x['previous'] = $wo->only(['received_at', 'due_at', 'completed_at', 'completed_by', 'verified_at', 'verified_by', 'closed_at', 'diagnosis', 'action_taken', 'function_test_result', 'hold_minutes']);
        $now = now();
        $t = $this->sla->targets($wo->priority, $now, $wo->property_id);

        return [
            'reopen_count' => $wo->reopen_count + 1, 'sla_cycle' => $wo->sla_cycle + 1,
            'received_at' => $now, 'response_due_at' => $t['response_due_at'], 'due_at' => $t['due_at'], 'hold_minutes' => 0,
            'completed_at' => null, 'completed_by' => null, 'verified_at' => null, 'verified_by' => null, 'closed_at' => null, 'closed_by' => null, 'restored_at' => null,
        ];
    }

    protected function doCancel(WorkOrder $wo, array $d, User $u, array &$x): array
    {
        $this->requireReason($d);
        $this->stopTimers($wo);

        return [];
    }

    // ---------------------------------------------------------------- biaya
    public function addCost(WorkOrder $wo, array $d, User $u): WoCostLine
    {
        if ($wo->status === 'closed' && ! $u->hasPermission('wo.cost_finalize')) {
            throw WorkflowException::invalid('Work order sudah ditutup; koreksi biaya dilakukan Finance.');
        }
        if (! $u->hasAnyPermission(['wo.execute', 'wo.assign', 'wo.cost_finalize'])) {
            throw WorkflowException::forbidden();
        }

        return $wo->costLines()->create([
            'type' => $d['type'], 'description' => $d['description'], 'quantity' => $d['quantity'] ?? 1, 'unit_cost' => $d['unit_cost'] ?? 0,
            'invoice_ref' => $d['invoice_ref'] ?? null, 'cost_status' => 'provisional', 'created_by' => $u->id,
            'source_type' => $d['source_type'] ?? null, 'source_id' => $d['source_id'] ?? null,
        ]);
    }

    /** Finance memfinalkan biaya; koreksi sesudahnya memakai baris reversal terpisah. */
    public function finalizeCosts(WorkOrder $wo, User $u, array $adjustments = []): void
    {
        if (! $u->hasPermission('wo.cost_finalize')) {
            throw WorkflowException::forbidden();
        }
        DB::transaction(function () use ($wo, $u, $adjustments) {
            foreach ($adjustments as $adj) {
                if ((float) ($adj['amount'] ?? 0) == 0.0) {
                    continue;
                }
                $wo->costLines()->create(['type' => $adj['type'] ?? 'vendor', 'description' => 'Koreksi invoice: '.($adj['description'] ?? '-'), 'quantity' => 1, 'unit_cost' => $adj['amount'], 'invoice_ref' => $adj['invoice_ref'] ?? null, 'cost_status' => 'final', 'created_by' => $u->id, 'finalized_by' => $u->id, 'finalized_at' => now()]);
            }
            $wo->costLines()->where('cost_status', 'provisional')->update(['cost_status' => 'final', 'finalized_by' => $u->id, 'finalized_at' => now()]);
            $wo->update(['cost_status' => 'final']);
            $this->event($wo, $wo->status, $wo->status, 'finalize_cost', 'Biaya difinalkan Finance');
        });
    }

    public function reverseCost(WoCostLine $line, string $reason, User $u): WoCostLine
    {
        if (! $u->hasPermission('wo.cost_finalize')) {
            throw WorkflowException::forbidden();
        }

        return WoCostLine::create([
            'work_order_id' => $line->work_order_id, 'type' => $line->type, 'description' => 'Reversal: '.$line->description.' — '.$reason,
            'quantity' => -1 * (float) $line->quantity, 'unit_cost' => $line->unit_cost, 'reverses_id' => $line->id,
            'cost_status' => $line->cost_status, 'created_by' => $u->id,
        ]);
    }

    // ---------------------------------------------------------------- bantu
    public function proposeRoomBlock(WorkOrder $wo, string $reason, User $u): RoomBlock
    {
        $room = $wo->location?->isRoom() ? $wo->location : $wo->asset?->location;
        if (! $room || ! $room->isRoom()) {
            throw WorkflowException::invalid('Work order tidak terkait kamar.');
        }
        $block = RoomBlock::create(['room_id' => $room->id, 'work_order_id' => $wo->id, 'status' => 'proposed', 'reason' => $reason, 'proposed_by' => $u->id]);
        $wo->update(['room_access_needed' => true]);
        $this->notify->send($this->notify->usersWith('room.block_confirm'), 'Usulan blokir kamar '.($room->room_ref ?: $room->name), $wo->number.': '.$reason.' — mohon konfirmasi Front Office (status PMS tidak diubah otomatis).', null, 'roomblock-'.$block->id, 'warning');

        return $block;
    }

    public function setAssetStatus(Asset $asset, string $status, string $reason, ?WorkOrder $wo = null): void
    {
        $asset->auditReason = $reason;
        $asset->statusSource = $wo ? ['work_order', $wo->id] : [];
        $asset->update(['operational_status' => $status]);
    }

    protected function stopTimers(WorkOrder $wo): void
    {
        foreach ($wo->timeLogs()->whereNull('ended_at')->get() as $log) {
            $log->update(['ended_at' => now(), 'approved_minutes' => (int) $log->started_at->diffInMinutes(now())]);
        }
    }

    public function hasApprovalAuthority(User $u, float $amount, int $propertyId): bool
    {
        $today = now()->timezone(config('assetcare.display_timezone'))->toDateString();
        $q = fn ($query) => $query->withoutGlobalScopes()->where('property_id', $propertyId)->where('object_type', 'work_order')
            ->whereDate('valid_from', '<=', $today)->where(fn ($w) => $w->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today))
            ->where(fn ($w) => $w->whereNull('max_amount')->orWhere('max_amount', '>=', $amount));

        return $q(ApprovalLimit::query())->where(fn ($w) => $w->where('user_id', $u->id)->orWhere('delegate_user_id', $u->id))->exists();
    }

    protected function requireReason(array $d): void
    {
        if (blank($d['reason'] ?? null)) {
            throw WorkflowException::invalid('Alasan wajib diisi.');
        }
    }

    public function event(WorkOrder $wo, ?string $from, string $to, string $action, ?string $reason = null, array $data = []): void
    {
        StatusEvent::create([
            'subject_type' => WorkOrder::class, 'subject_id' => $wo->id, 'from_status' => $from, 'to_status' => $to,
            'action' => $action, 'reason' => $reason, 'data' => $data ?: null, 'actor_id' => auth()->id(), 'occurred_at' => now(),
        ]);
    }

    protected function afterAction(WorkOrder $wo, string $action, User $actor): void
    {
        if ($wo->pm_schedule_id && in_array($action, ['verify', 'cancel'], true)) {
            app(PlannedMaintenanceService::class)->onWorkOrderFinished($wo, $action === 'verify');
        }
        $url = \App\Filament\Resources\WorkOrders\WorkOrderResource::getUrl('view', ['record' => $wo]);
        $title = $wo->number.' · '.WorkOrder::STATUSES[$wo->status];
        match ($action) {
            'submit' => $this->notify->send($this->notify->usersWith('wo.approve')->reject(fn ($u) => $u->id === $actor->id), 'Persetujuan diperlukan: '.$wo->number, $wo->title, $url, null, 'warning'),
            'assign' => $this->notify->send(User::whereIn('id', array_merge([$wo->pic_id], $wo->team_ids ?? []))->get(), 'Tugas baru: '.$wo->number, $wo->title.' — '.($wo->location?->full_path ?? ''), $url, 'wo-assign-'.$wo->id.'-'.$wo->version),
            'complete' => $this->notify->send($this->notify->usersWith('wo.verify')->reject(fn ($u) => $u->id === $actor->id), 'Menunggu verifikasi: '.$wo->number, $wo->title, $url),
            'reject_back' => $this->notify->send($wo->pic, 'Hasil ditolak: '.$wo->number, 'Dikembalikan pemeriksa untuk diperbaiki.', $url, null, 'danger'),
            'approve_direct' => $wo->is_emergency && (float) $wo->estimated_cost > 0 ? $this->notify->send($this->notify->usersWith('wo.approve'), 'Persetujuan susulan (darurat): '.$wo->number, $wo->title, $url, null, 'danger') : null,
            default => null,
        };
    }
}
