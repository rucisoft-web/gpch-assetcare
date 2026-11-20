<?php

namespace App\Services;

use App\Models\InstallationEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Konfirmasi pemasangan (bagian 26): pilih kamar → pindai label yang sudah ditempel → periksa identitas → konfirmasi.
 * Scan biasa hanya membuka aset; tidak otomatis menyatakan pemasangan.
 */
class InstallationService
{
    public function __construct(protected QrResolver $resolver, protected LabelService $labels) {}

    /** @return array{ok:bool, level:string, message:string, event?:InstallationEvent} */
    public function confirm(Location $room, string $input, User $user, string $status = 'installed', ?string $note = null, ?string $photo = null): array
    {
        if (! $user->hasPermission('label.install')) {
            throw WorkflowException::forbidden();
        }
        $r = $this->resolver->resolve($input);
        if (! in_array($r['type'], ['asset', 'group'], true) || empty($r['label'])) {
            return ['ok' => false, 'level' => 'danger', 'message' => $r['message'] ?? 'Label tidak aktif untuk target ini.'];
        }
        $label = $r['label'];
        $target = $r['asset'] ?? $r['group'];
        $loc = $target->location;
        $inRoom = $loc && ((int) $loc->id === (int) $room->id || str_starts_with((string) $loc->sort_key, (string) $room->sort_key.'/'));
        if (! $inRoom) {
            return ['ok' => false, 'level' => 'warning', 'message' => 'Label milik '.($target->asset_code ?? $target->group_code).' tercatat di '.($loc?->full_path ?? 'lokasi lain').', bukan '.$room->name.'. Konfirmasi ditolak — periksa apakah label tertukar.'];
        }

        return DB::transaction(function () use ($label, $target, $room, $user, $status, $note, $photo, $r) {
            $label = $label->newQuery()->whereKey($label->id)->lockForUpdate()->first();
            $last = $label->installationEvents()->first();
            if ($status === 'installed' && $last?->status === 'installed' && (int) $last->location_id === (int) $target->location_id) {
                return ['ok' => true, 'level' => 'info', 'message' => ($target->asset_code ?? $target->group_code).' sudah tercatat terpasang ('.$last->occurred_at->timezone(config('assetcare.display_timezone'))->format('d/m H:i').'). Tidak dicatat ganda.'];
            }
            $printItem = $label->printItems()->latest('id')->first();
            $ev = InstallationEvent::create([
                'asset_label_id' => $label->id, 'asset_id' => $r['asset']?->id ?? null, 'quantity_group_id' => $r['group']?->id ?? null,
                'location_id' => $target->location_id, 'print_item_id' => $printItem?->id, 'status' => $status, 'note' => $note,
                'photo_path' => $photo, 'actor_id' => $user->id, 'occurred_at' => now(),
            ]);
            $this->labels->syncInstallStatus($label);
            $warn = $printItem && $printItem->location_snapshot !== $target->location?->labelText() ? ' Perhatian: tulisan lokasi pada label ("'.$printItem->location_snapshot.'") tidak sesuai, pertimbangkan cetak ulang.' : '';
            if (! $printItem) {
                $warn .= ' Label ini belum tercatat pernah dicetak.';
            }

            return ['ok' => true, 'level' => $warn ? 'warning' : 'success', 'message' => ($target->asset_code ?? $target->group_code).' · '.($target->name).' — '.InstallationEvent::STATUSES[$status].'.'.$warn, 'event' => $ev];
        });
    }
}
