<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Petugas identifikasi (hanya peran field-officer) tidak memakai panel admin: selalu dialihkan ke menu Lapangan. */
class RedirectFieldOfficer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isFieldOnly() && ! $request->routeIs('filament.admin.auth.logout')) {
            return redirect()->route('field.home');
        }

        return $next($request);
    }
}
