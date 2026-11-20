<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;

/**
 * Kode aset = LOKASI-JENIS-URUT, mis. MB-08-819-TV-01 (gedung MB, lantai 08, kamar 819, televisi, unit ke-1).
 * Lokasi dari kode lokasi (maks. 18 karakter), jenis dari kode kategori. Urutan per lokasi+jenis, tidak dipakai ulang.
 * Bila aset pindah lokasi atau ganti kategori, kode diperbarui; kode lama disimpan di former_codes.
 */
class AssetCode
{
    public const LOC_MAX = 18;

    public static function locationPart(Location $l): string
    {
        $c = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $l->code));
        if (strlen($c) <= self::LOC_MAX) {
            return $c;
        }
        $seg = array_values(array_filter(explode('-', $c), 'strlen'));
        $head = count($seg) > 2 ? implode('-', array_slice($seg, 0, 2)) : ($seg[0] ?? '');
        $tail = count($seg) > 2 ? end($seg) : implode('', array_slice($seg, 1));
        // nomor di akhir nama ruang dipertahankan (KORIDORLOBBI3 → KORIDORLO3) agar ruang bernomor tidak tertukar
        preg_match('/^(.*?)(\d*)$/', $tail, $m);
        $room = $m[2] !== '' ? substr($m[1], 0, max(1, self::LOC_MAX - strlen($head) - 1 - strlen($m[2]))).$m[2]
            : substr($tail, 0, max(1, self::LOC_MAX - strlen($head) - 1));

        return rtrim(substr($head.'-'.$room, 0, self::LOC_MAX + 4), '-');
    }

    public static function prefix(?int $locationId, ?int $categoryId): ?string
    {
        $loc = $locationId ? Location::withoutGlobalScopes()->find($locationId) : null;
        $cat = $categoryId ? AssetCategory::withoutGlobalScopes()->find($categoryId) : null;
        if (! $loc || ! $cat || blank($loc->code) || blank($cat->code)) {
            return null;
        }

        return self::locationPart($loc).'-'.strtoupper($cat->code);
    }

    /** Kode berikutnya untuk lokasi+jenis aset ini; null bila lokasi/kategori belum diisi. */
    public static function next(Asset $a): ?string
    {
        $prefix = self::prefix($a->location_id, $a->category_id);
        if (! $prefix) {
            return null;
        }
        do {
            $code = sprintf('%s-%02d', $prefix, Sequence::increment($a->property_id, 'A:'.$prefix));
        } while (Asset::withoutGlobalScopes()->where('property_id', $a->property_id)->where('asset_code', $code)->whereKeyNot($a->id ?? 0)->exists());

        return $code;
    }

    public static function appendFormer(?string $former, ?string $old): ?string
    {
        if (blank($old)) {
            return $former;
        }
        $list = array_filter(explode(';', (string) $former));
        if (! in_array($old, $list, true)) {
            $list[] = $old;
        }

        return implode(';', $list);
    }
}
