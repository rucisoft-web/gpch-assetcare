<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Produksi: hanya referensi (izin, peran, departemen, kategori, SLA, Super Admin).
     * Lokal / ASSETCARE_SEED_DEMO=true: tambah data contoh pilot (terpisah dari produksi).
     */
    public function run(): void
    {
        $this->call(ReferenceSeeder::class);

        if (! app()->isProduction() || config('assetcare.seed_demo')) {
            $this->call(DemoSeeder::class);
        }
    }
}
