<?php

namespace App\Support\Help;

use App\Models\Role;
use App\Models\User;

/** Panduan penggunaan per peran (isi di roles.php). */
class RoleGuide
{
    public static function all(): array
    {
        $raw = require __DIR__.'/roles.php';
        $names = Role::pluck('name', 'slug')->all();
        $desc = Role::pluck('description', 'slug')->all();
        $out = [];
        foreach ($raw as $slug => $g) {
            if (isset($g['sama'])) {
                $g = array_merge($raw[$g['sama']], ['sama' => $g['sama']]);
            }
            $g['slug'] = $slug;
            $g['nama'] = $names[$slug] ?? $slug;
            $g['deskripsi'] = $desc[$slug] ?? null;
            $out[$slug] = $g;
        }

        return $out;
    }

    public static function get(string $slug): ?array
    {
        return static::all()[$slug] ?? null;
    }

    /** Peran pengguna lebih dulu, lalu peran lain. @return array{0: array<int,string>, 1: array} */
    public static function forUser(?User $user): array
    {
        $all = static::all();
        $mine = $user ? $user->roles->pluck('slug')->filter(fn ($s) => isset($all[$s]))->values()->all() : [];

        return [$mine, $all];
    }

    public static function image(string $name): string
    {
        return asset('help/peran/'.$name.'.jpg').'?v='.substr((string) @md5_file(public_path('help/peran/'.$name.'.jpg')), 0, 8);
    }

    public const PERANGKAT = ['web' => 'Komputer (web)', 'ponsel' => 'Ponsel', 'keduanya' => 'Komputer atau ponsel'];
}
