<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Identitas aplikasi & perusahaan. Nama aplikasi generik "Asset Care"; nama, logo, alamat, warna
 * perusahaan diatur di Pengaturan › Identitas perusahaan (tersimpan di app_settings).
 */
class Brand
{
    public const APP_NAME = 'Asset Care';

    public const DEFAULT_COLOR = '#1F6F5C';

    /** field => [label, default] */
    public const FIELDS = [
        'company_name' => 'Nama perusahaan / properti',
        'company_short' => 'Nama singkat',
        'legal_name' => 'Nama badan hukum',
        'address' => 'Alamat',
        'city' => 'Kota',
        'phone' => 'Telepon',
        'email' => 'Email',
        'website' => 'Situs web',
        'npwp' => 'NPWP',
        'tagline' => 'Tagline',
        'label_heading' => 'Judul di label QR',
        'primary_color' => 'Warna utama',
        'show_ornament' => 'Tampilkan ornamen',
        'logo_path' => 'Logo',
        'logo_dark_path' => 'Logo (mode gelap)',
        'favicon_path' => 'Ikon (favicon)',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return AppSetting::get($key, $default);
    }

    public static function appName(): string
    {
        return self::APP_NAME;
    }

    public static function companyName(): string
    {
        return (string) self::get('company_name', 'Perusahaan Anda');
    }

    public static function companyShort(): string
    {
        return (string) self::get('company_short', self::get('company_name', ''));
    }

    /** "Asset Care · GPCH" di header, judul tab, MFA. */
    public static function panelName(): string
    {
        $short = self::companyShort();

        return $short ? self::APP_NAME.' · '.$short : self::APP_NAME;
    }

    public static function tagline(): string
    {
        return (string) self::get('tagline', 'Pengelolaan aset & pemeliharaan');
    }

    public static function labelHeading(): string
    {
        return (string) self::get('label_heading', self::companyShort() ?: self::APP_NAME);
    }

    public static function color(): string
    {
        $c = (string) self::get('primary_color', self::DEFAULT_COLOR);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : self::DEFAULT_COLOR;
    }

    public static function showOrnament(): bool
    {
        return (bool) self::get('show_ornament', false);
    }

    /** URL logo: unggahan perusahaan, atau logo generik Asset Care. */
    protected static function fileExists(?string $path): bool
    {
        return $path && Storage::disk('local')->exists($path);
    }

    public static function logoUrl(bool $dark = false): string
    {
        if ($dark && self::fileExists(self::get('logo_dark_path'))) {
            return route('brand.asset', ['type' => 'logo-dark', 'v' => substr(md5((string) self::get('logo_dark_path')), 0, 8)]);
        }
        if (self::fileExists(self::get('logo_path'))) {
            return route('brand.asset', ['type' => 'logo', 'v' => substr(md5((string) self::get('logo_path')), 0, 8)]);
        }

        return asset($dark ? 'images/logo-assetcare-dark.svg' : 'images/logo-assetcare.svg');
    }

    public static function faviconUrl(): string
    {
        return self::fileExists(self::get('favicon_path')) ? route('brand.asset', ['type' => 'favicon', 'v' => substr(md5((string) self::get('favicon_path')), 0, 8)]) : asset('favicon.png');
    }

    /** Path berkas lokal logo (untuk PDF), null bila memakai logo generik. */
    public static function logoFile(): ?string
    {
        $p = self::get('logo_path');

        return $p && Storage::disk('local')->exists($p) ? Storage::disk('local')->path($p) : null;
    }

    public static function contactLine(): string
    {
        return collect([self::get('address'), self::get('city'), self::get('phone'), self::get('email'), self::get('website')])->filter()->join(' · ');
    }
}
