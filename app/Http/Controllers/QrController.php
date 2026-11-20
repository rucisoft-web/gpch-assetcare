<?php

namespace App\Http\Controllers;

use App\Services\ScanService;
use Illuminate\Http\Request;

class QrController extends Controller
{
    public function show(Request $request, string $token, ScanService $scan)
    {
        if (! $request->user()) {
            // simpan tujuan, lalu login (SSO/login biasa)
            session()->put('url.intended', $request->fullUrl());

            return redirect('/admin/login');
        }
        // petugas identifikasi membuka aset di menu Lapangan
        if ($request->user()->isFieldOnly() || $request->query('m') === 'lapangan') {
            return redirect()->route('field.resolve', ['kode' => url('/q/'.$token)]);
        }
        $r = $scan->handle(url('/q/'.$token), $request->user(), 'url');

        return $r['url'] ? redirect($r['url']) : response()->view('qr.invalid', ['message' => $r['message']], 410);
    }
}
