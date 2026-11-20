<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Fmt
{
    public static function num(float|int|string|null $v, int $dec = 0): string
    {
        if ($v === null || $v === '') {
            return '-';
        }

        return number_format((float) $v, $dec, ',', '.');
    }

    public static function rp(float|int|string|null $v): string
    {
        return $v === null || $v === '' ? '-' : 'Rp '.number_format((float) $v, 0, ',', '.');
    }

    public static function pct(float|int|null $v, int $dec = 1): string
    {
        return $v === null ? 'N/A' : number_format((float) $v, $dec, ',', '.').'%';
    }

    public static function tz(): string
    {
        return config('assetcare.display_timezone', 'Asia/Jakarta');
    }

    /** Timestamp UTC → waktu lokal (Asia/Jakarta). */
    public static function dt(?CarbonInterface $v, string $format = 'd/m/Y H:i'): string
    {
        return $v ? $v->copy()->timezone(self::tz())->translatedFormat($format) : '-';
    }

    public static function date(?CarbonInterface $v, string $format = 'd M Y'): string
    {
        return $v ? $v->copy()->timezone(self::tz())->translatedFormat($format) : '-';
    }

    /** Durasi menit → "2 j 15 m" / "3 h 4 j". */
    public static function minutes(int|float|null $m): string
    {
        if ($m === null) {
            return '-';
        }
        $m = (int) round($m);
        $neg = $m < 0;
        $m = abs($m);
        $d = intdiv($m, 1440);
        $h = intdiv($m % 1440, 60);
        $i = $m % 60;
        $out = $d ? $d.' h '.$h.' j' : ($h ? $h.' j '.$i.' m' : $i.' m');

        return ($neg ? '-' : '').$out;
    }

    /** Status warna Filament (success/warning/danger/gray) → kelas badge tema. */
    public static function badgeClass(?string $color): string
    {
        return match ($color) {
            'success' => 'arah-hijau',
            'warning' => 'arah-kuning',
            'danger' => 'arah-merah',
            'info', 'primary' => 'arah-biru',
            default => 'arah-abu',
        };
    }

    /** Cegah formula injection saat ekspor CSV. */
    public static function csvSafe(mixed $v): string
    {
        $s = (string) ($v ?? '');

        return $s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$s : $s;
    }
}
