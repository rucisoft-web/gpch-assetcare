<?php

namespace App\Support;

/**
 * Ukuran label QR yang umum dipakai: lembar A4 untuk printer biasa, label gulung untuk printer
 * label thermal/Bluetooth (Zebra, Niimbot, Phomemo, Xprinter, Brother RJ), dan pita laminasi (Brother TZe, Epson LK).
 */
class LabelSize
{
    public const DEFAULT = 'a4';

    /** @return array<string, array{label: string, width: ?int, height: ?int}> */
    public static function all(): array
    {
        return [
            'a4' => ['label' => 'A4 · 2 kolom (printer biasa / stiker A4)', 'width' => null, 'height' => null],
            '50x30' => ['label' => '50 × 30 mm · paling umum (label gulung)', 'width' => 50, 'height' => 30],
            '40x30' => ['label' => '40 × 30 mm (label gulung)', 'width' => 40, 'height' => 30],
            '50x25' => ['label' => '50 × 25 mm (label gulung)', 'width' => 50, 'height' => 25],
            '60x40' => ['label' => '60 × 40 mm (aset besar, mesin)', 'width' => 60, 'height' => 40],
            '30x20' => ['label' => '30 × 20 mm (aset kecil)', 'width' => 30, 'height' => 20],
            'tape24' => ['label' => 'Pita 24 mm, panjang 60 mm (Brother TZe / Epson LK)', 'width' => 60, 'height' => 24],
            'tape18' => ['label' => 'Pita 18 mm, panjang 50 mm (Brother TZe / Epson LK)', 'width' => 50, 'height' => 18],
        ];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn (array $size) => $size['label'], static::all());
    }

    public static function normalize(?string $key): string
    {
        return array_key_exists((string) $key, static::all()) ? (string) $key : static::DEFAULT;
    }

    /** @return array{label: string, width: ?int, height: ?int} */
    public static function get(string $key): array
    {
        return static::all()[static::normalize($key)];
    }
}
