<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Hak akses resource berbasis izin (deny by default).
 * Resource menetapkan: $viewPermission, $editPermission (null = tidak ada yang boleh mengubah lewat form).
 */
trait HasPermissions
{
    protected static function user(): ?\App\Models\User
    {
        return Auth::user();
    }

    protected static function allows(?string $permission): bool
    {
        $user = static::user();

        return $permission !== null && $user !== null && $user->hasPermission($permission);
    }

    public static function canViewAny(): bool
    {
        return static::allows(static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::allows(static::$viewPermission);
    }

    public static function canCreate(): bool
    {
        return static::allows(static::$editPermission);
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows(static::$editPermission);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
