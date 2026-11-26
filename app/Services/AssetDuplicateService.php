<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetLabel;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hapus entri aset dobel dengan menggabungkannya ke aset yang dipertahankan.
 * Foto, lampiran, catatan, dokumen, label (berikut riwayatnya) dan slot template dipindah ke aset yang dipertahankan;
 * isian yang kosong di aset yang dipertahankan diisi dari entri dobel. Entri dobel lalu dihapus (tercatat di jejak audit).
 * Entri yang sudah punya riwayat transaksi (WO, laporan kerusakan, mutasi, komponen, pembacaan kondisi) tidak bisa dihapus.
 */
class AssetDuplicateService
{
    public const COPY_FIELDS = ['brand', 'model', 'serial_number', 'photo_path', 'department_id', 'custodian_id', 'custodian_unit',
        'condition_grade', 'criticality', 'vendor_id', 'purchase_date', 'in_service_date', 'warranty_until', 'specification', 'pm_interval_days', 'legacy_code'];

    /** @return string[] alasan entri tidak boleh dihapus */
    public function blockers(Asset $dup): array
    {
        $b = [];
        $count = fn (string $table, string $col = 'asset_id') => DB::table($table)->where($col, $dup->id)->count();
        if ($n = $count('work_orders')) {
            $b[] = $n.' work order';
        }
        if ($n = $count('service_requests')) {
            $b[] = $n.' laporan kerusakan';
        }
        if ($n = $count('asset_transfer_items')) {
            $b[] = $n.' mutasi';
        }
        if ($n = $count('asset_components', 'parent_asset_id') + $count('asset_components', 'component_asset_id') + Asset::withoutGlobalScopes()->where('parent_id', $dup->id)->count()) {
            $b[] = $n.' relasi komponen';
        }
        if ($n = $count('condition_readings')) {
            $b[] = $n.' pembacaan kondisi';
        }

        return $b;
    }

    public function merge(Asset $dup, Asset $keep, string $reason, User $user): Asset
    {
        if (! $user->hasPermission('asset.delete')) {
            throw WorkflowException::forbidden();
        }
        if ($dup->is($keep)) {
            throw WorkflowException::invalid('Pilih aset lain sebagai aset yang dipertahankan.');
        }
        if ($dup->property_id !== $keep->property_id) {
            throw WorkflowException::invalid('Aset yang dipertahankan harus di properti yang sama.');
        }
        if ($b = $this->blockers($dup)) {
            throw WorkflowException::invalid('Entri ini sudah punya riwayat ('.implode(', ', $b).'), jadi tidak dihapus. Hentikan pemakaian (retired) atau koreksi datanya.');
        }

        return DB::transaction(function () use ($dup, $keep, $reason, $user) {
            $dup = Asset::withoutGlobalScopes()->lockForUpdate()->findOrFail($dup->id);
            $keep = Asset::withoutGlobalScopes()->lockForUpdate()->findOrFail($keep->id);

            // 1. isian kosong di aset yang dipertahankan
            $fill = [];
            foreach (self::COPY_FIELDS as $f) {
                if (blank($keep->{$f}) && filled($dup->{$f})) {
                    $fill[$f] = $dup->{$f};
                }
            }

            // 2. label: label aktif entri dobel dipakai bila aset yang dipertahankan belum punya; selain itu dicabut. Riwayat label ikut pindah.
            $keepHasLabel = AssetLabel::where('asset_id', $keep->id)->where('status', 'active')->exists();
            foreach (AssetLabel::where('asset_id', $dup->id)->get() as $label) {
                if ($label->status === 'active' && ! $keepHasLabel) {
                    $label->update(['asset_id' => $keep->id, 'active_target_key' => 'a:'.$keep->id]);
                    $keepHasLabel = true;
                    $fill['installation_status'] = $keep->installation_status ?: $dup->installation_status;
                } elseif ($label->status === 'active') {
                    $label->update(['asset_id' => $keep->id, 'status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $user->id,
                        'revoke_reason' => 'Entri dobel '.$dup->asset_code.' digabung', 'active_target_key' => null]);
                } else {
                    $label->update(['asset_id' => $keep->id]);
                }
            }
            DB::table('label_print_items')->where('asset_id', $dup->id)->update(['asset_id' => $keep->id]);
            DB::table('installation_events')->where('asset_id', $dup->id)->update(['asset_id' => $keep->id]);

            // 3. slot template kamar
            $svc = app(RoomTemplateService::class);
            if ($slotA = $dup->slotAssignment()->first()) {
                $slot = $slotA->slot;
                $svc->release($slotA, 'Entri dobel '.$dup->asset_code.' dihapus');
                if ($slot && ! $keep->slotAssignment()->exists() && $keep->location_id === $dup->location_id) {
                    $svc->assign($slot, $keep, 'Pengganti entri dobel '.$dup->asset_code, null, $user);
                }
            }

            // 4. foto, lampiran, catatan, dokumen, keuangan
            Attachment::where('attachable_type', $dup->getMorphClass())->where('attachable_id', $dup->id)->update(['attachable_id' => $keep->id]);
            Comment::where('commentable_type', $dup->getMorphClass())->where('commentable_id', $dup->id)->update(['commentable_id' => $keep->id]);
            foreach (DB::table('asset_document_links')->where('asset_id', $dup->id)->pluck('asset_document_id') as $docId) {
                DB::table('asset_document_links')->insertOrIgnore(['asset_document_id' => $docId, 'asset_id' => $keep->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            if (! DB::table('asset_financials')->where('asset_id', $keep->id)->exists()) {
                DB::table('asset_financials')->where('asset_id', $dup->id)->update(['asset_id' => $keep->id]);
            }

            $note = trim(($keep->notes ? $keep->notes."\n" : '').'['.now()->format('d/m/Y').'] Entri dobel '.$dup->asset_code.' ('.$dup->name.') digabung ke aset ini oleh '.$user->name.': '.$reason);
            $keep->forceFill($fill + ['notes' => $note])->save();

            // 5. hapus entri dobel (jejak audit menyimpan isinya)
            $dup->deletingAsDuplicate = true;
            $dup->delete();

            return $keep->fresh();
        });
    }
}
