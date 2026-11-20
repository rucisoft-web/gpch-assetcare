<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\CurrentProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'assetcare:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Membuat / memulihkan akun Super Admin Asset Care';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Nama', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $pass = $this->option('password') ?: password('Kata sandi (min. 10 karakter, huruf & angka)', required: true);
        $v = Validator::make(compact('email', 'pass'), ['email' => 'email', 'pass' => [Password::defaults()]]);
        if ($v->fails()) {
            $this->error(implode(' ', $v->errors()->all()));

            return self::FAILURE;
        }
        $role = Role::where('slug', 'super-admin')->first();
        if (! $role) {
            $this->error('Peran belum ada. Jalankan: php artisan db:seed');

            return self::FAILURE;
        }
        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name, 'password' => $pass, 'is_active' => true, 'must_change_password' => false, 'job_title' => 'Super Admin',
            'property_id' => app(CurrentProperty::class)->id(), 'email_verified_at' => now(),
        ]);
        $user->roles()->syncWithoutDetaching([$role->id => ['property_id' => null]]);
        $this->info("Super Admin {$email} siap. Login di ".url('/admin'));

        return self::SUCCESS;
    }
}
