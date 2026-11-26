<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Wajib ganti kata sandi saat login pertama (users.must_change_password). */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user?->must_change_password && ! $request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout', 'filament.admin.auth.multi-factor-authentication.*')) {
            Notification::make()->title('Silakan ganti kata sandi Anda terlebih dahulu')
                ->body('Kata sandi minimal 10 karakter, mengandung huruf dan angka.')->warning()->send();

            return redirect()->route('filament.admin.auth.profile');
        }

        // Wajib 2FA (ASSETCARE_MFA_REQUIRED=true) untuk administrator & approver yang belum mengaktifkannya
        if ($user && config('assetcare.mfa_required') && blank($user->app_authentication_secret)
            && $user->roles->pluck('slug')->intersect(config('assetcare.mfa_roles'))->isNotEmpty()
            && ! $request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout', 'filament.admin.auth.multi-factor-authentication.*')) {
            Notification::make()->title('Aktifkan verifikasi dua langkah (2FA)')
                ->body('Peran Anda wajib memakai aplikasi autentikator. Klik "Siapkan" pada bagian Autentikasi multi-faktor.')->warning()->send();

            return redirect()->route('filament.admin.auth.profile');
        }

        return $next($request);
    }
}
