<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Property;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Identitas perusahaan awal. Hanya diisi bila pengaturan masih kosong, sehingga perubahan di
 * Pengaturan › Identitas perusahaan tidak pernah ditimpa saat update. Instalasi GPCH mendapat identitas GPCH;
 * instalasi lain memakai identitas generik "Asset Care" sampai diisi.
 */
class BrandSeeder extends Seeder
{
    /** berkas bawaan GPCH: path di storage => sumber di public/ */
    public const GPCH_FILES = [
        'branding/logo-gpch.png' => 'images/logo-gpch.png',
        'branding/logo-gpch-dark.png' => 'images/logo-gpch-dark.png',
        'branding/favicon-gpch.png' => 'favicon.png',
    ];

    public function run(): void
    {
        if (AppSetting::query()->exists()) {
            // instalasi lewat impor SQL: pengaturan ada tetapi berkas logo bawaan belum tersalin
            foreach (self::GPCH_FILES as $target => $src) {
                if (AppSetting::where('value', $target)->exists() && ! Storage::disk('local')->exists($target) && is_file(public_path($src))) {
                    Storage::disk('local')->put($target, file_get_contents(public_path($src)));
                }
            }

            return;
        }
        $p = Property::first();
        if (! $p || strtoupper($p->code) !== 'GPCH') {
            AppSetting::put(['company_name' => $p?->name ?? 'Perusahaan Anda', 'company_short' => $p?->code, 'show_ornament' => false]);

            return;
        }
        $disk = Storage::disk('local');
        $copy = function (string $public, string $target) use ($disk) {
            $src = public_path($public);
            if (is_file($src)) {
                $disk->put($target, file_get_contents($src));

                return $target;
            }

            return null;
        };
        AppSetting::put([
            'company_name' => 'Grand Pasundan Convention Hotel',
            'company_short' => 'GPCH',
            'city' => 'Bandung',
            'website' => 'grandpasundanhotel.com',
            'tagline' => 'Pengelolaan aset & pemeliharaan',
            'label_heading' => 'GPCH Asset Care',
            'primary_color' => '#006632',
            'show_ornament' => true,
            'logo_path' => $copy('images/logo-gpch.png', 'branding/logo-gpch.png'),
            'logo_dark_path' => $copy('images/logo-gpch-dark.png', 'branding/logo-gpch-dark.png'),
            'favicon_path' => $copy('favicon.png', 'branding/favicon-gpch.png'),
        ]);
    }
}
