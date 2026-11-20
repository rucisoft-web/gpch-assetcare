<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Pengaturan kunci–nilai (identitas perusahaan & tampilan). Dibaca sekali per permintaan, di-cache. */
class AppSetting extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public const CACHE_KEY = 'app_settings.all';

    protected static ?array $memo = null;

    /** @return array<string, string|null> */
    public static function all_(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }
        try {
            return static::$memo = Cache::rememberForever(self::CACHE_KEY, fn () => Schema::hasTable('app_settings') ? static::query()->pluck('value', 'key')->all() : []);
        } catch (Throwable) {
            return []; // sebelum instalasi / basis data belum siap
        }
    }

    public static function flush(): void
    {
        static::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $v = static::all_()[$key] ?? null;

        return $v === null || $v === '' ? $default : $v;
    }

    /** @param array<string, mixed> $values */
    public static function put(array $values, ?int $by = null): void
    {
        foreach ($values as $k => $v) {
            $row = static::firstOrNew(['key' => $k]);
            $row->value = is_bool($v) ? ($v ? '1' : '0') : ($v === null ? null : (string) $v);
            $row->updated_by = $by;
            if ($row->isDirty()) {
                $row->auditReason = 'Pengaturan identitas diubah';
                $row->save();
            }
        }
        static::flush();
    }
}
