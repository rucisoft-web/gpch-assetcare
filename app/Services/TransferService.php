<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\SlotAssignment;
use App\Models\StatusEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mutasi (bagian 13): draft → submitted → approved → dispatched → received → closed; rejected/cancelled dengan alasan.
 * Lokasi resmi berubah setelah penerimaan dikonfirmasi; kode & QR tetap.
 */
class TransferService
{
    public function __construct(protected LabelService $labels, protected RoomTemplateService $rooms, protected NotificationService $notify) {}

    public function submit(AssetTransfer $t, User $u): void
    {
        $this->move($t, ['draft'], 'submitted', 'submit', $u, fn () => $t->items()->exists() ?: throw WorkflowException::invalid('Tambahkan minimal satu aset.'));
        $this->notify->send($this->notify->usersWith('transfer.approve')->reject(fn ($x) => $x->id === $u->id), 'Persetujuan mutasi '.$t->number, $t->reason);
    }

    public function approve(AssetTransfer $t, User $u): void
    {
        if (! $u->hasPermission('transfer.approve')) {
            throw WorkflowException::forbidden();
        }
        if ((int) $t->requested_by === (int) $u->id) {
            throw WorkflowException::forbidden('Pengaju mutasi tidak dapat menyetujui sendiri.');
        }
        $this->move($t, ['submitted'], 'approved', 'approve', $u, null, ['approved_by' => $u->id, 'approved_at' => now()]);
    }

    public function reject(AssetTransfer $t, string $reason, User $u): void
    {
        if (! $u->hasPermission('transfer.approve')) {
            throw WorkflowException::forbidden();
        }
        $this->move($t, ['submitted'], 'rejected', 'reject', $u, null, ['status_reason' => $reason], $reason);
    }

    public function cancel(AssetTransfer $t, string $reason, User $u): void
    {
        $this->move($t, ['draft', 'submitted', 'approved'], 'cancelled', 'cancel', $u, null, ['status_reason' => $reason], $reason);
    }

    public function dispatch(AssetTransfer $t, User $u): void
    {
        if (! $u->hasPermission('transfer.receive')) {
            throw WorkflowException::forbidden();
        }
        $this->move($t, ['approved'], 'dispatched', 'dispatch', $u, function () use ($t) {
            foreach ($t->items as $i) {
                $i->asset->update(['in_transit' => true]);
            }
        }, ['dispatched_by' => $u->id, 'dispatched_at' => now()]);
    }

    /** Penerima memindai QR & memeriksa kondisi; satu commit untuk status, lokasi & histori. */
    public function receive(AssetTransfer $t, array $conditions, User $u): void
    {
        if (! $u->hasPermission('transfer.receive')) {
            throw WorkflowException::forbidden();
        }
        $this->move($t, ['dispatched'], 'received', 'receive', $u, function () use ($t, $conditions, $u) {
            foreach ($t->items()->with('asset')->get() as $i) {
                $asset = Asset::whereKey($i->asset_id)->lockForUpdate()->first();
                $asset->auditReason = 'Mutasi '.$t->number;
                $asset->statusSource = ['asset_transfer', $t->id];
                $cond = $conditions[$i->id] ?? $asset->condition_grade;
                $asset->update(array_filter([
                    'location_id' => $t->to_location_id, 'department_id' => $t->to_department_id, 'in_transit' => false, 'condition_grade' => $cond,
                ], fn ($v) => $v !== null));
                $i->update(['condition_in' => $cond]);
                // keluar dari slot kamar lama: pemetaan dilepas, slot terlihat kosong (tidak membuat aset baru)
                if ($a = SlotAssignment::where('active_asset_key', $asset->id)->first()) {
                    if ((int) $a->slot?->room_id !== (int) $t->to_location_id) {
                        $this->rooms->release($a, 'Mutasi '.$t->number);
                    }
                }
                $this->labels->flagRelabelIfLocationChanged($asset->fresh(['activeLabel', 'location']), $u);
            }
        }, ['received_by' => $u->id, 'received_at' => now()]);
    }

    public function close(AssetTransfer $t, User $u): void
    {
        $this->move($t, ['received'], 'closed', 'close', $u);
    }

    protected function move(AssetTransfer $t, array $from, string $to, string $action, User $u, ?callable $fn = null, array $extra = [], ?string $reason = null): void
    {
        DB::transaction(function () use ($t, $from, $to, $action, $u, $fn, $extra, $reason) {
            $fresh = AssetTransfer::whereKey($t->id)->lockForUpdate()->first();
            if ((int) $fresh->version !== (int) $t->version) {
                throw WorkflowException::conflict();
            }
            if (! in_array($fresh->status, $from, true)) {
                throw WorkflowException::invalid('Aksi tidak berlaku untuk status '.AssetTransfer::STATUSES[$fresh->status].'.');
            }
            if (in_array($action, ['reject', 'cancel'], true) && blank($reason)) {
                throw WorkflowException::invalid('Alasan wajib diisi.');
            }
            if ($fn) {
                $fn();
            }
            $old = $fresh->status;
            $fresh->update($extra + ['status' => $to, 'version' => $fresh->version + 1]);
            StatusEvent::create(['subject_type' => AssetTransfer::class, 'subject_id' => $t->id, 'from_status' => $old, 'to_status' => $to, 'action' => $action, 'reason' => $reason, 'actor_id' => $u->id, 'occurred_at' => now()]);
            $t->setRawAttributes($fresh->getAttributes(), true);
        });
    }
}
