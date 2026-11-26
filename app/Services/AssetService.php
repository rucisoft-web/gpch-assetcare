<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetComponent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssetService
{
    /** Verifikasi fisik: keberadaan, posisi, model, nomor seri, kondisi & penanggung jawab. */
    public function verify(Asset $asset, array $d, User $user): Asset
    {
        if (! $user->hasPermission('asset.verify')) {
            throw WorkflowException::forbidden();
        }
        $found = (bool) ($d['found'] ?? true);
        $asset->auditReason = $found ? 'Verifikasi fisik' : 'Tidak ditemukan saat verifikasi: '.($d['note'] ?? '-');
        $asset->fill(array_filter([
            'brand' => $d['brand'] ?? null, 'model' => $d['model'] ?? null, 'serial_number' => $d['serial_number'] ?? null,
            'condition_grade' => $d['condition_grade'] ?? null, 'custodian_id' => $d['custodian_id'] ?? null,
            'custodian_unit' => $d['custodian_unit'] ?? null, 'department_id' => $d['department_id'] ?? null,
            'ownership' => $d['ownership'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
        $asset->verification_status = $found ? 'verified' : 'discrepancy';
        $asset->verified_by = $user->id;
        $asset->verified_at = now();
        $asset->last_inspected_at = now();
        $asset->save();

        return $asset;
    }

    public function activate(Asset $asset, User $user): Asset
    {
        if (! $user->hasPermission('asset.activate')) {
            throw WorkflowException::forbidden();
        }
        $missing = $asset->missingForActivation();
        if ($missing) {
            throw WorkflowException::invalid('Lengkapi dulu: '.implode(', ', $missing).'.');
        }
        $asset->auditReason = 'Aktivasi aset';
        $asset->update(['lifecycle_status' => 'active']);

        return $asset;
    }

    /** retired = berhenti dipakai tetapi masih dimiliki. disposed/lost melalui proses penghapusan & opname (rilis B). */
    public function retire(Asset $asset, string $reason, User $user): void
    {
        if (! $user->hasPermission('asset.activate')) {
            throw WorkflowException::forbidden();
        }
        $asset->auditReason = $reason;
        $asset->update(['lifecycle_status' => 'retired', 'operational_status' => 'out_of_service']);
    }

    public function attachComponent(Asset $parent, Asset $component, ?string $reason, User $user, ?int $workOrderId = null, ?Asset $replaces = null): void
    {
        if (! $user->hasPermission('asset.edit')) {
            throw WorkflowException::forbidden();
        }
        DB::transaction(function () use ($parent, $component, $reason, $user, $workOrderId, $replaces) {
            if ($replaces) {
                $this->detachComponent($replaces, 'Diganti '.$component->asset_code.($reason ? ': '.$reason : ''), $user, $workOrderId);
            }
            $active = AssetComponent::where('active_component_key', $component->id)->lockForUpdate()->first();
            if ($active) {
                throw WorkflowException::invalid($component->asset_code.' masih terpasang pada induk lain. Lepaskan dulu.');
            }
            $component->auditReason = 'Dipasang ke '.$parent->asset_code;
            $component->update(['parent_id' => $parent->id]); // memicu pencegahan siklus
            AssetComponent::create([
                'parent_asset_id' => $parent->id, 'component_asset_id' => $component->id, 'installed_at' => now(), 'work_order_id' => $workOrderId,
                'reason' => $reason, 'replaced_component_id' => $replaces?->id, 'active_component_key' => $component->id, 'created_by' => $user->id,
            ]);
        });
    }

    public function detachComponent(Asset $component, string $reason, User $user, ?int $workOrderId = null): void
    {
        DB::transaction(function () use ($component, $reason, $workOrderId) {
            AssetComponent::where('active_component_key', $component->id)->lockForUpdate()->get()
                ->each(fn ($c) => $c->update(['removed_at' => now(), 'active_component_key' => null, 'reason' => trim(($c->reason ? $c->reason.' · ' : '').'Dilepas: '.$reason), 'work_order_id' => $c->work_order_id ?? $workOrderId]));
            $component->auditReason = 'Dilepas: '.$reason;
            $component->update(['parent_id' => null]);
        });
    }
}
