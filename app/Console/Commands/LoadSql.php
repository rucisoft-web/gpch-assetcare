<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Dipakai skrip deploy (mode install-data): muat berkas SQL ke database KOSONG tanpa perlu klien mysql/phpMyAdmin. */
class LoadSql extends Command
{
    protected $signature = 'assetcare:load-sql {file}';

    protected $description = 'Muat berkas SQL (hasil mysqldump) ke database kosong';

    protected $hidden = true;

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }
        if (DB::select("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE()")[0]->n > 0) {
            $this->error('Database tidak kosong. Gunakan database baru yang kosong.');

            return self::FAILURE;
        }
        $buf = '';
        $n = 0;
        $fh = fopen($file, 'r');
        while (($line = fgets($fh)) !== false) {
            $t = ltrim($line);
            if ($buf === '' && ($t === '' || str_starts_with($t, '--'))) {
                continue;
            }
            $buf .= $line;
            if (str_ends_with(rtrim($line), ';')) {
                DB::unprepared($buf);
                $buf = '';
                $n++;
            }
        }
        fclose($fh);
        if (trim($buf) !== '') {
            DB::unprepared($buf);
            $n++;
        }
        $this->info("SQL dimuat ({$n} perintah).");

        return self::SUCCESS;
    }
}
