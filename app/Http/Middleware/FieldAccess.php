<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Menu Lapangan: wajib login, aktif, izin field.identify; ganti sandi pertama kali di halaman lapangan sendiri. */
class FieldAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('field.login'));
        }
        if (! $user->is_active) {
            auth()->logout();

            return redirect()->route('field.login')->withErrors(['email' => 'Akun tidak aktif.']);
        }
        if (! $user->hasPermission('field.identify')) {
            return $user->isFieldOnly() ? abort(403) : redirect('/admin');
        }
        if ($user->must_change_password && ! $request->routeIs('field.password', 'field.password.save', 'field.logout')) {
            return redirect()->route('field.password');
        }

        return $next($request);
    }
}
