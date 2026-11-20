<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\ConditionParameter;
use App\Models\Property;
use Illuminate\Database\Seeder;

/** Parameter kondisi bawaan (predictive maintenance). Idempoten: nilai yang sudah diubah tidak ditimpa. */
class MaintenanceSeeder extends Seeder
{
    /** kode, nama, satuan, meter?, kategori, arah, waspada, kritis, prioritas, tiap (hari), panduan */
    public const PARAMETERS = [
        ['JAM-OPS', 'Jam operasi (hour meter)', 'jam', true, null, 'above', null, null, 'P3', 7, 'Catat angka hour meter; dipakai untuk PM berbasis jam operasi.'],
        ['SUHU-CHILL', 'Suhu ruang pendingin (chiller)', '°C', false, 'CHILL', 'above', 5, 8, 'P2', 1, 'Cek seal pintu, kondensor & kompresor; pindahkan bahan bila > 8 °C lebih dari 2 jam.'],
        ['SUHU-FREEZ', 'Suhu freezer', '°C', false, 'FREEZ', 'above', -15, -12, 'P2', 1, 'Cek defrost, kipas evaporator & seal pintu.'],
        ['TEK-BOILER', 'Tekanan kerja boiler', 'bar', false, 'BOILER', 'above', 8, 10, 'P1', 1, 'Lewat batas kritis: hentikan pemanas, periksa safety valve & pressure switch.'],
        ['GETAR', 'Getaran mesin berputar (ISO 10816)', 'mm/s', false, null, 'above', 4.5, 7.1, 'P2', 30, 'Cek kelurusan (alignment), bantalan (bearing) & baut dudukan.'],
        ['SUHU-PANEL', 'Suhu titik panas panel (thermal)', '°C', false, 'PANEL', 'above', 60, 80, 'P2', 90, 'Kencangkan terminal, cek beban fasa & kondisi MCB/MCCB.'],
        ['TEK-APAR', 'Tekanan APAR (manometer)', 'bar', false, 'APAR', 'below', 12, 10, 'P2', 30, 'Isi ulang atau ganti tabung; pastikan pin & segel utuh.'],
        ['AKI-GEN', 'Tegangan aki starter genset', 'V', false, 'GENSET', 'below', 24.5, 23.5, 'P2', 7, 'Cas / ganti aki; cek charger & terminal.'],
    ];

    public function run(): void
    {
        foreach (Property::all() as $p) {
            foreach (self::PARAMETERS as [$code, $name, $unit, $meter, $cat, $dir, $warn, $crit, $prio, $every, $guide]) {
                ConditionParameter::withoutGlobalScopes()->firstOrCreate(['property_id' => $p->id, 'code' => $code], [
                    'name' => $name, 'unit' => $unit, 'is_meter' => $meter,
                    'category_id' => $cat ? AssetCategory::withoutGlobalScopes()->where('property_id', $p->id)->where('code', $cat)->value('id') : null,
                    'direction' => $dir, 'warning_value' => $warn, 'critical_value' => $crit, 'critical_priority' => $prio,
                    'reading_interval_days' => $every, 'guidance' => $guide, 'is_active' => true,
                ]);
            }
        }
    }
}
