<?php

namespace App\Http\Controllers\Filament;

use Filament\Facades\Filament;
use Filament\Http\Controllers\RedirectToTenantController;
use Illuminate\Http\RedirectResponse;

/**
 * Pengguna yang belum punya keanggotaan perusahaan tidak lagi melihat 404:
 * admin platform diarahkan ke /platform, pengguna lain ke halaman penjelasan.
 */
class RedirectToTenant extends RedirectToTenantController
{
    public function __invoke(): RedirectResponse
    {
        $user = Filament::auth()->user();

        if ($user && ! Filament::getUserDefaultTenant($user)) {
            return $user->is_platform_admin ? redirect('/platform') : redirect()->route('no-company');
        }

        return parent::__invoke();
    }
}
