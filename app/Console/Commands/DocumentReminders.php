<?php

namespace App\Console\Commands;

use App\Services\DocumentService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Hitung ulang status register dokumen lalu kirim pengingat masa berlaku (H-90/60/30/7/0, eskalasi GM mulai H-30). */
class DocumentReminders extends Command
{
    protected $signature = 'assetcare:document-reminders {--date= : tanggal acuan (YYYY-MM-DD), untuk uji}';

    protected $description = 'Perbarui status dokumen aset dan kirim pengingat masa berlaku (harian lewat scheduler)';

    public function handle(DocumentService $docs): int
    {
        $today = $this->option('date') ? CarbonImmutable::parse($this->option('date'), \App\Support\Fmt::tz())->startOfDay() : null;
        $changed = $docs->refreshStatuses($today);
        $sent = $docs->sendReminders($today);
        $this->info("Status diperbarui: {$changed}; pengingat terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
