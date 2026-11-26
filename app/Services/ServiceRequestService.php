<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\ServiceRequest;
use App\Models\StatusEvent;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/** Alur laporan: submitted → triaged → linked_to_work_order → resolved → closed; rejected/duplicate dengan alasan. */
class ServiceRequestService
{
    public function __construct(protected SlaService $sla, protected NotificationService $notify) {}

    public static function suggestPriority(?string $impact, bool $safety): string
    {
        return match (true) {
            $safety || $impact === 'keselamatan' => 'P1',
            in_array($impact, ['kamar_tidak_dijual', 'acara_terganggu'], true) => 'P2',
            $impact === 'kosmetik' => 'P4',
            default => 'P3',
        };
    }

    /** Buat laporan. client_event_uuid mencegah tiket ganda saat kirim ulang. */
    public function create(array $data, User $user, ?string $clientUuid = null): ServiceRequest
    {
        if ($clientUuid && ($existing = ServiceRequest::where('reporter_id', $user->id)->where('client_event_uuid', $clientUuid)->first())) {
            return $existing;
        }

        return DB::transaction(function () use ($data, $user, $clientUuid) {
            $asset = ! empty($data['asset_id']) ? Asset::find($data['asset_id']) : null;
            $safety = (bool) ($data['is_safety'] ?? false);
            $priority = self::suggestPriority($data['impact'] ?? null, $safety);
            $received = now();
            $targets = $this->sla->targets($priority, $received);
            $sr = ServiceRequest::create([
                'asset_id' => $asset?->id,
                'quantity_group_id' => $data['quantity_group_id'] ?? null,
                'location_id' => $data['location_id'] ?? $asset?->location_id,
                'reporter_id' => $user->id,
                'reporter_department_id' => $user->department_id,
                'reporter_contact' => $data['reporter_contact'] ?? $user->phone,
                'category' => $data['category'] ?? 'lainnya',
                'symptom' => $data['symptom'],
                'impact' => $data['impact'] ?? null,
                'is_safety' => $safety || ($data['impact'] ?? null) === 'keselamatan',
                'priority' => $priority,
                'status' => 'submitted',
                'received_at' => $received,
                'response_due_at' => $targets['response_due_at'],
                'room_block_proposed' => (bool) ($data['room_block_proposed'] ?? false),
                'client_event_uuid' => $clientUuid,
            ]);
            $this->event($sr, null, 'submitted', 'submit', null, ['priority' => $priority]);
            DB::afterCommit(fn () => $this->notify->send(
                $this->notify->usersWith('request.triage'),
                $sr->number.' · '.$priority.' · '.ServiceRequest::CATEGORIES[$sr->category],
                \Illuminate\Support\Str::limit($sr->symptom, 120).' — '.($sr->location?->full_path ?? '-'),
                \App\Filament\Resources\ServiceRequests\ServiceRequestResource::getUrl('view', ['record' => $sr]),
                'sr-new-'.$sr->id,
                $priority === 'P1' ? 'danger' : ($priority === 'P2' ? 'warning' : 'info'),
            ));

            return $sr;
        });
    }

    /** Respons manusia pertama (pengakuan & penilaian petugas). */
    public function acknowledge(ServiceRequest $sr, User $user): void
    {
        DB::transaction(function () use ($sr, $user) {
            $sr = ServiceRequest::whereKey($sr->id)->lockForUpdate()->first();
            if ($sr->response_at) {
                return;
            }
            $sr->update(['response_at' => now(), 'responded_by' => $user->id]);
            $this->event($sr, $sr->status, $sr->status, 'acknowledge', 'Laporan diakui petugas');
        });
    }

    public function triage(ServiceRequest $sr, array $data, User $user): ServiceRequest
    {
        $this->authorize($user, 'request.triage');

        return DB::transaction(function () use ($sr, $data, $user) {
            $sr = ServiceRequest::whereKey($sr->id)->lockForUpdate()->first();
            if (! in_array($sr->status, ['submitted', 'triaged'], true)) {
                throw WorkflowException::invalid('Laporan berstatus '.ServiceRequest::STATUSES[$sr->status].' tidak dapat ditriase ulang.');
            }
            $old = $sr->priority;
            $new = $data['priority'] ?? $old;
            if ($new !== $old && blank($data['reason'] ?? null)) {
                throw WorkflowException::invalid('Perubahan prioritas wajib disertai alasan.');
            }
            $payload = [
                'priority' => $new,
                'is_safety' => (bool) ($data['is_safety'] ?? $sr->is_safety),
                'asset_id' => $data['asset_id'] ?? $sr->asset_id,
                'location_id' => $data['location_id'] ?? $sr->location_id,
                'category' => $data['category'] ?? $sr->category,
                'status' => 'triaged',
                'triaged_at' => now(),
                'triaged_by' => $user->id,
                'response_at' => $sr->response_at ?? now(),
                'responded_by' => $sr->responded_by ?? $user->id,
            ];
            $eventData = ['priority_old' => $old, 'priority_new' => $new];
            if ($new !== $old) {
                $eventData['response_due_old'] = $sr->response_due_at?->toIso8601String();
                $payload['response_due_at'] = $this->sla->targets($new, $sr->received_at)['response_due_at'];
            }
            $sr->auditReason = $data['reason'] ?? null;
            $from = $sr->status;
            $sr->update($payload);
            $this->event($sr, $from, 'triaged', 'triage', $data['reason'] ?? null, $eventData);

            return $sr;
        });
    }

    /** Buat work order dari satu atau beberapa laporan sejenis (pelapor tetap mendapat pembaruan). */
    public function createWorkOrder(array $requestIds, array $data, User $user): WorkOrder
    {
        $this->authorize($user, 'request.triage');

        return DB::transaction(function () use ($requestIds, $data, $user) {
            $requests = ServiceRequest::whereIn('id', $requestIds)->lockForUpdate()->get();
            if ($requests->isEmpty()) {
                throw WorkflowException::invalid('Pilih minimal satu laporan.');
            }
            foreach ($requests as $r) {
                if (! in_array($r->status, ['submitted', 'triaged'], true)) {
                    throw WorkflowException::invalid($r->number.' sudah '.ServiceRequest::STATUSES[$r->status].'.');
                }
            }
            $first = $requests->sortBy('received_at')->first();
            $priority = $data['priority'] ?? $requests->min('priority');
            $targets = $this->sla->targets($priority, $first->received_at);
            $wo = WorkOrder::create([
                'type' => $priority === 'P1' ? 'emergency' : ($data['type'] ?? 'corrective'),
                'source' => 'request',
                'title' => $data['title'] ?? \Illuminate\Support\Str::limit($first->symptom, 120),
                'description' => $data['description'] ?? $requests->map(fn ($r) => $r->number.': '.$r->symptom)->join("\n"),
                'asset_id' => $data['asset_id'] ?? $first->asset_id,
                'location_id' => $data['location_id'] ?? $first->location_id,
                'priority' => $priority,
                'is_safety' => $requests->contains('is_safety', true),
                'is_emergency' => $priority === 'P1',
                'status' => 'draft',
                'received_at' => $first->received_at,
                'response_due_at' => $targets['response_due_at'],
                'due_at' => $targets['due_at'],
                'sla_calendar' => $targets['calendar'],
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'needs_vendor' => (bool) ($data['needs_vendor'] ?? false),
                'room_access_needed' => $requests->contains('room_block_proposed', true),
            ]);
            app(WorkOrderService::class)->event($wo, null, 'draft', 'create', 'Dibuat dari '.$requests->pluck('number')->join(', '));
            foreach ($requests as $r) {
                $this->linkInternal($r, $wo, $user);
            }

            return $wo;
        });
    }

    public function link(ServiceRequest $sr, WorkOrder $wo, User $user): void
    {
        $this->authorize($user, 'request.triage');
        DB::transaction(function () use ($sr, $wo, $user) {
            $sr = ServiceRequest::whereKey($sr->id)->lockForUpdate()->first();
            if (! in_array($sr->status, ['submitted', 'triaged'], true)) {
                throw WorkflowException::invalid('Laporan ini tidak dapat ditautkan.');
            }
            if (! $wo->isOpen()) {
                throw WorkflowException::invalid('Work order tujuan sudah tidak terbuka.');
            }
            $this->linkInternal($sr, $wo, $user);
        });
    }

    protected function linkInternal(ServiceRequest $sr, WorkOrder $wo, User $user): void
    {
        $sr->workOrders()->syncWithoutDetaching([$wo->id => ['linked_by' => $user->id]]);
        $from = $sr->status;
        $sr->update([
            'status' => 'linked_to_work_order',
            'response_at' => $sr->response_at ?? now(),
            'responded_by' => $sr->responded_by ?? $user->id,
            'triaged_at' => $sr->triaged_at ?? now(),
            'triaged_by' => $sr->triaged_by ?? $user->id,
        ]);
        $this->event($sr, $from, 'linked_to_work_order', 'link', $wo->number);
    }

    public function reject(ServiceRequest $sr, string $reason, User $user, ?int $duplicateOf = null): void
    {
        $this->authorize($user, 'request.triage');
        if (blank($reason)) {
            throw WorkflowException::invalid('Alasan wajib diisi.');
        }
        DB::transaction(function () use ($sr, $reason, $user, $duplicateOf) {
            $sr = ServiceRequest::whereKey($sr->id)->lockForUpdate()->first();
            if (! in_array($sr->status, ['submitted', 'triaged'], true)) {
                throw WorkflowException::invalid('Laporan sudah diproses.');
            }
            $to = $duplicateOf ? 'duplicate' : 'rejected';
            $from = $sr->status;
            $sr->update([
                'status' => $to, 'status_reason' => $reason, 'duplicate_of_id' => $duplicateOf, 'closed_at' => now(),
                'response_at' => $sr->response_at ?? now(), 'responded_by' => $sr->responded_by ?? $user->id,
            ]);
            $this->event($sr, $from, $to, $to === 'duplicate' ? 'mark_duplicate' : 'reject', $reason);
            DB::afterCommit(fn () => $this->notify->send($sr->reporter, $sr->number.' '.ServiceRequest::STATUSES[$to], $reason, null, null, 'warning'));
        });
    }

    /** Dipanggil saat WO terverifikasi / ditutup / dibuka ulang. */
    public function syncFromWorkOrder(WorkOrder $wo, string $woStatus): void
    {
        $map = ['verified' => 'resolved', 'closed' => 'closed', 'reopened' => 'linked_to_work_order'];
        if (! isset($map[$woStatus])) {
            return;
        }
        $to = $map[$woStatus];
        foreach ($wo->requests()->get() as $sr) {
            if (in_array($sr->status, ['rejected', 'duplicate'], true) || $sr->status === $to) {
                continue;
            }
            $from = $sr->status;
            $sr->update(['status' => $to, 'resolved_at' => $to === 'resolved' ? now() : $sr->resolved_at, 'closed_at' => $to === 'closed' ? now() : null]);
            $this->event($sr, $from, $to, 'sync_wo', $wo->number.' '.WorkOrder::STATUSES[$woStatus]);
            if ($to !== 'linked_to_work_order') {
                DB::afterCommit(fn () => $this->notify->send($sr->reporter, $sr->number.' — '.ServiceRequest::STATUSES[$to], 'Pekerjaan '.$wo->number.' '.WorkOrder::STATUSES[$woStatus].'. Mohon konfirmasi layanan bila masih ada kendala.', null, null, 'success'));
            }
        }
    }

    public function event(ServiceRequest $sr, ?string $from, string $to, string $action, ?string $reason = null, array $data = []): void
    {
        StatusEvent::create([
            'subject_type' => ServiceRequest::class, 'subject_id' => $sr->id, 'from_status' => $from, 'to_status' => $to,
            'action' => $action, 'reason' => $reason, 'data' => $data ?: null, 'actor_id' => auth()->id(), 'occurred_at' => now(),
        ]);
    }

    protected function authorize(User $user, string $perm): void
    {
        if (! $user->hasPermission($perm)) {
            throw WorkflowException::forbidden();
        }
    }
}
