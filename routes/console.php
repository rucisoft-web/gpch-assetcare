<?php

use App\Models\Asset;
use App\Models\Company;
use App\Models\PreventivePlan;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
| Perintah operasional Ruci Asset Care.
| Aplikasi tidak membutuhkan cron; semua perhitungan berjalan saat data disimpan atau dibuka.
*/

Artisan::command('asset:create-admin {email} {name} {--password=}', function (string $email, string $name) {
    $password = $this->option('password') ?: $this->secret('Kata sandi (min. 10 karakter)');

    if (strlen((string) $password) < 10) {
        $this->error('Kata sandi minimal 10 karakter.');

        return 1;
    }

    $user = User::updateOrCreate(['email' => $email], [
        'name' => $name, 'password' => $password, 'is_platform_admin' => true, 'is_active' => true,
    ]);

    $this->info("Admin platform siap: {$user->email}. Masuk lewat /platform untuk membuat perusahaan dan pengguna.");
})->purpose('Buat atau perbarui admin platform');

Artisan::command('asset:demo', function () {
    $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
    $this->call('db:seed', ['--class' => 'Database\\Seeders\\GeneralAffairsDemoSeeder', '--force' => true]);
    $this->info('Data contoh siap di /app/gpch. Akun: manajer@gpch.demo, teknisi@gpch.demo, supervisor@gpch.demo, umum@gpch.demo, gm@gpch.demo (sandi: ASSET_DEMO_PASSWORD).');
})->purpose('Muat data contoh Grand Pasundan Convention Hotel');

Artisan::command('asset:remove-demo', function () {
    $company = Company::where('slug', 'gpch')->where('is_demo', true)->first();

    if (! $company || ! $this->confirm("Hapus perusahaan contoh {$company->name} beserta seluruh datanya?")) {
        return 0;
    }

    DB::transaction(function () use ($company) {
        $userIds = $company->memberships()->withoutGlobalScopes()->pluck('user_id');
        $id = $company->id;

        // Urutan anak → induk karena kategori dan lokasi dilindungi ON DELETE RESTRICT.
        DB::table('maintenance_requests')->where('company_id', $id)->update(['work_order_id' => null]);
        foreach (['legal_documents', 'stock_movements', 'supply_requests', 'supply_items', 'vehicle_bookings', 'vehicle_fuel_logs', 'vehicle_services', 'vehicles', 'room_bookings', 'meeting_rooms'] as $table) {
            DB::table($table)->where('company_id', $id)->delete();
        }
        foreach (['capex_proposals', 'asset_risks', 'work_orders', 'preventive_plans', 'maintenance_requests', 'inspections', 'assets'] as $table) {
            DB::table($table)->where('company_id', $id)->delete();
        }
        DB::table('asset_template_lines')->whereIn('asset_template_id', DB::table('asset_templates')->where('company_id', $id)->pluck('id'))->delete();
        DB::table('locations')->where('company_id', $id)->update(['parent_id' => null]);

        $company->delete();
        User::whereIn('id', $userIds)->where('email', 'like', '%@gpch.demo')->delete();
    });

    $this->info('Data contoh dihapus.');
})->purpose('Hapus data contoh GPCH');

Artisan::command('asset:doctor', function () {
    $checks = [
        'PHP >= 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
        'Ekstensi pdo_mysql' => extension_loaded('pdo_mysql'),
        'Ekstensi mbstring' => extension_loaded('mbstring'),
        'Ekstensi intl' => extension_loaded('intl'),
        'Ekstensi fileinfo (unggah foto)' => extension_loaded('fileinfo'),
        'APP_KEY terisi' => filled(config('app.key')),
        'APP_URL bukan localhost (QR harus bisa dibuka dari HP)' => ! str_contains((string) config('app.url'), 'localhost') && ! str_contains((string) config('app.url'), '127.0.0.1'),
        'Koneksi database' => rescue(fn () => (bool) DB::select('select 1'), false, false),
        'Penyimpanan berkas bisa ditulis' => rescue(fn () => Storage::disk('local')->put('.doctor', 'ok') && Storage::disk('local')->delete('.doctor'), false, false),
    ];

    $this->table(['Pemeriksaan', 'Hasil'], collect($checks)->map(fn ($ok, $name) => [$name, $ok ? 'OK' : 'GAGAL'])->values()->all());

    return in_array(false, $checks, true) ? 1 : 0;
})->purpose('Periksa kesiapan server');

Artisan::command('asset:summary', function () {
    foreach (Company::where('is_active', true)->get() as $company) {
        app(CurrentCompany::class)->runAs($company, function () use ($company) {
            $this->line(sprintf('%-40s aset: %5d   PM terlambat: %3d', $company->name, Asset::count(), PreventivePlan::overdue()->count()));
        });
    }
})->purpose('Ringkasan jumlah aset per perusahaan');
