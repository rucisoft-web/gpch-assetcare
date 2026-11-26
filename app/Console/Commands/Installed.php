<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Dipakai skrip deploy: exit 0 bila database sudah berisi pengguna (instalasi tidak boleh diulang). */
class Installed extends Command
{
    protected $signature = 'assetcare:installed';

    protected $description = 'Cek apakah database sudah terpasang & berisi data';

    protected $hidden = true;

    public function handle(): int
    {
        try {
            $yes = Schema::hasTable('users') && User::query()->exists();
        } catch (Throwable) {
            $yes = false;
        }
        $this->line($yes ? 'terpasang' : 'kosong');

        return $yes ? self::SUCCESS : self::FAILURE;
    }
}
