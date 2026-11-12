<?php

namespace App\Filament\Support;

use App\Models\Department;
use Filament\Notifications\Notification;
use LogicException;

/** Pembantu kecil untuk modul bagian umum. */
class Domain
{
    /** Jalankan aksi model; aturan bisnis yang dilanggar (LogicException) tampil sebagai notifikasi, bukan halaman galat. */
    public static function attempt(callable $callback, string $success): bool
    {
        try {
            $callback();
        } catch (LogicException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return false;
        }

        Notification::make()->title($success)->success()->send();

        return true;
    }

    /** @return array<int, string> */
    public static function departmentOptions(): array
    {
        return Department::query()->where('is_active', true)->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function quantity(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
