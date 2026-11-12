<?php

use App\Http\Controllers\QrController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');
Route::get('/login', fn () => redirect('/app/login'))->name('login');

// Halaman publik hasil pindai QR aset.
Route::get('/qr/{token}', [QrController::class, 'show'])->name('qr.show')->where('token', '[A-Za-z0-9]{20,64}');
Route::post('/qr/{token}/lapor', [QrController::class, 'report'])->name('qr.report')->middleware('throttle:qr-report')->where('token', '[A-Za-z0-9]{20,64}');

Route::middleware('auth')->group(function () {
    Route::get('/label-qr', [QrController::class, 'labels'])->name('qr.labels');
    Route::view('/tanpa-perusahaan', 'no-company')->name('no-company');
});
