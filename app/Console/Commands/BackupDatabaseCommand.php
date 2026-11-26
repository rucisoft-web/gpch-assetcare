<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'assetcare:backup-db';

    protected $description = 'Backup database ke .sql bertanggal (ZIP) di storage/app/private/backups';

    public function handle(BackupService $svc): int
    {
        $path = $svc->create(null, 'jadwal');
        $this->info('Backup: '.$path.' ('.round($svc->disk()->size($path) / 1024).' KB)');

        return self::SUCCESS;
    }
}
