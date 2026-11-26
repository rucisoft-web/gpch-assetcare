<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran server per properti (baris dikunci, aman untuk permintaan bersamaan).
 *   assetCode()  → GPCH-AST-000001 (permanen, tidak dipakai ulang, tanpa tahun/lokasi)
 *   next('LK')   → LK-2026-00001   (laporan kerusakan), WO-2026-00001, MUT-…, LBL-…, GEN-…
 */
class Sequence
{
    public static function increment(int $propertyId, string $prefix, int $year = 0): int
    {
        return DB::transaction(function () use ($propertyId, $prefix, $year) {
            $row = DB::table('sequences')->where(['property_id' => $propertyId, 'prefix' => $prefix, 'year' => $year])->lockForUpdate()->first();
            if (! $row) {
                DB::table('sequences')->insertOrIgnore(['property_id' => $propertyId, 'prefix' => $prefix, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $row = DB::table('sequences')->where(['property_id' => $propertyId, 'prefix' => $prefix, 'year' => $year])->lockForUpdate()->first();
            }
            $n = $row->last_number + 1;
            DB::table('sequences')->where('id', $row->id)->update(['last_number' => $n, 'updated_at' => now()]);

            return $n;
        });
    }

    public static function assetCode(int $propertyId): string
    {
        $code = Property::whereKey($propertyId)->value('code') ?? 'X';

        return sprintf('%s-AST-%06d', $code, self::increment($propertyId, 'AST'));
    }

    public static function groupCode(int $propertyId): string
    {
        $code = Property::whereKey($propertyId)->value('code') ?? 'X';

        return sprintf('%s-QTY-%05d', $code, self::increment($propertyId, 'QTY'));
    }

    public static function next(string $prefix, int $propertyId): string
    {
        $year = (int) now()->timezone(Fmt::tz())->year;

        return sprintf('%s-%d-%05d', $prefix, $year, self::increment($propertyId, $prefix, $year));
    }
}
