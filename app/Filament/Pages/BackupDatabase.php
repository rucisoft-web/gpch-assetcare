<?php

namespace App\Filament\Pages;

use App\Filament\Support\F;
use App\Services\BackupService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/** Backup database ke .sql bertanggal dalam ZIP; bagian IT dapat membuat & mengunduhnya. */
class BackupDatabase extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Backup database';

    protected static ?string $title = 'Backup database';

    protected static ?string $slug = 'backup-database';

    protected string $view = 'filament.pages.backup-database';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('backup.manage');
    }

    public function getBackupsProperty(): Collection
    {
        return app(BackupService::class)->list();
    }

    public function create(): void
    {
        @set_time_limit(600);
        $path = null;
        F::attempt(function () use (&$path) {
            $path = app(BackupService::class)->create(auth()->user());
        });
        if ($path) {
            Notification::make()->title('Backup selesai')->body(basename($path))->success()->send();
        }
    }

    public function download(string $name)
    {
        $svc = app(BackupService::class);
        try {
            $path = $svc->path($name);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return null;
        }
        Log::info('Backup database diunduh', ['file' => $name, 'by' => auth()->id()]);

        return $svc->disk()->download($path, $name);
    }

    public function remove(string $name): void
    {
        F::attempt(fn () => app(BackupService::class)->delete($name), 'Backup dihapus');
    }
}
