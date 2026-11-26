<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LabelPdfController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Logo & ikon perusahaan (Pengaturan › Identitas perusahaan); publik karena tampil di halaman login.
Route::get('/brand/{type}', [BrandAssetController::class, 'show'])->where('type', 'logo|logo-dark|favicon')->name('brand.asset');

// URL pada label QR. Tamu diarahkan ke login lalu kembali ke sini; token disimpan hanya dalam bentuk hash.
Route::get('/q/{token}', [QrController::class, 'show'])->where('token', '[A-Za-z0-9_-]{20,64}')
    ->middleware('throttle:60,1')->name('qr.show');

Route::middleware(['auth'])->group(function () {
    Route::get('/labels/{batch}/pdf', [LabelPdfController::class, 'show'])->name('labels.pdf');
    Route::get('/labels/uji/{profile}', [LabelPdfController::class, 'test'])->name('labels.test');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::get('/assets/{asset}/photo', [AttachmentController::class, 'assetPhoto'])->name('assets.photo');
    Route::get('/bantuan/{key}', [HelpController::class, 'show'])->where('key', '[a-z0-9.\-]+')->name('help.show');
    Route::get('/reports/{report}/export', [ReportExportController::class, 'export'])->name('reports.export');
});

// Menu Lapangan: tampilan ponsel petugas identifikasi aset (terpisah dari panel admin).
Route::prefix('lapangan')->name('field.')->controller(\App\Http\Controllers\Field\FieldController::class)->group(function () {
    Route::get('/login', 'loginForm')->name('login');
    Route::post('/login', 'login')->middleware('throttle:20,1')->name('login.post');
    Route::middleware(\App\Http\Middleware\FieldAccess::class)->group(function () {
        Route::post('/logout', 'logout')->name('logout');
        Route::get('/sandi', 'passwordForm')->name('password');
        Route::post('/sandi', 'passwordSave')->name('password.save');
        Route::get('/', 'home')->name('home');
        Route::get('/scan', 'scan')->name('scan');
        Route::get('/panduan', 'guide')->name('guide');
        Route::get('/buka', 'resolve')->name('resolve');
        Route::get('/lokasi/{location?}', 'location')->name('location');
        Route::get('/cari', 'search')->name('search');
        Route::get('/aset/{asset}', 'asset')->name('asset');
        Route::post('/aset/{asset}', 'identify')->name('identify');
        Route::get('/lokasi/{location}/pasang', 'install')->name('install');
        Route::post('/lokasi/{location}/pasang', 'installScan')->name('install.scan');
        Route::post('/lokasi/{location}/pasang/{label}', 'installMark')->name('install.mark');
        Route::get('/lokasi/{location}/temuan', 'newForm')->name('new');
        Route::post('/lokasi/{location}/temuan', 'newSave')->name('new.save');
        Route::get('/kelompok/{group}', 'group')->name('group');
        Route::post('/kelompok/{group}', 'groupCount')->name('group.count');
    });
});
