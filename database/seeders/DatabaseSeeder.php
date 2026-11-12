<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if ($email = config('asset.admin.email')) {
            User::updateOrCreate(['email' => $email], [
                'name' => 'Admin Platform',
                'password' => config('asset.admin.password') ?: str()->password(16),
                'is_platform_admin' => true,
                'is_active' => true,
            ]);
            $this->command?->info("Admin platform: {$email}");
        }

        if (config('asset.demo.enabled')) {
            $this->call([DemoSeeder::class, GeneralAffairsDemoSeeder::class]);

            if ($email) {
                // Admin platform juga bisa membuka data contoh sebagai manajer.
                $company = \App\Models\Company::where('slug', 'gpch')->first();
                User::where('email', $email)->first()?->memberships()->firstOrCreate(['company_id' => $company->id], ['role' => 'manajer', 'is_active' => true]);
            }
        }
    }
}
