<?php

namespace App\Console\Commands;

use App\Services\PlannedMaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Buat work order preventive untuk jadwal PM yang jatuh tempo (berbasis tanggal & meter). */
class PmGenerate extends Command
{
    protected $signature = 'assetcare:pm-generate {--date= : tanggal acuan (YYYY-MM-DD), untuk uji}';

    protected $description = 'Buat WO preventive untuk rencana PM yang jatuh tempo (harian lewat scheduler)';

    public function handle(PlannedMaintenanceService $pm): int
    {
        $today = $this->option('date') ? CarbonImmutable::parse($this->option('date'), \App\Support\Fmt::tz())->startOfDay() : null;
        $n = $pm->generateDue($today);
        $this->info("Work order PM dibuat: {$n}.");

        return self::SUCCESS;
    }
}
