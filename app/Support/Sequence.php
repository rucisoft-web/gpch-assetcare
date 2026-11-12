<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Nomor dokumen berurutan per perusahaan per tahun, misalnya AST/2026/00001.
 */
class Sequence
{
    public const PREFIXES = [
        'asset' => 'AST',
        'inspection' => 'INS',
        'request' => 'MR',
        'work_order' => 'WO',
        'room_booking' => 'RR',
        'vehicle_booking' => 'KD',
        'supply_request' => 'PB',
    ];

    public static function next(string $code, int $companyId, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        $number = DB::transaction(function () use ($code, $companyId, $year) {
            DB::table('sequences')->insertOrIgnore(['company_id' => $companyId, 'code' => $code, 'year' => $year, 'last_number' => 0]);

            $row = DB::table('sequences')
                ->where(['company_id' => $companyId, 'code' => $code, 'year' => $year])
                ->lockForUpdate()
                ->first();

            DB::table('sequences')->where('id', $row->id)->update(['last_number' => $row->last_number + 1]);

            return $row->last_number + 1;
        });

        return sprintf('%s/%d/%05d', self::PREFIXES[$code] ?? strtoupper($code), $year, $number);
    }
}
