<?php

namespace App\Filament\Pages;

use App\Models\ScanLog;
use App\Services\ScanService;
use Filament\Pages\Page;

/** M03 Scan QR dari ponsel: membuka kartu aset / formulir laporan. */
class ScanQr extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|\UnitEnum|null $navigationGroup = 'Umum';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Scan QR';

    protected static ?string $title = 'Scan QR aset';

    protected string $view = 'filament.pages.scan-qr';

    public ?string $message = null;

    public string $level = 'info';

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    public function resolve(string $text, string $channel = 'scanner'): void
    {
        $r = app(ScanService::class)->handle(mb_substr($text, 0, 500), auth()->user(), in_array($channel, ['scanner', 'manual'], true) ? $channel : 'scanner');
        if ($r['url']) {
            $this->redirect($r['url']);

            return;
        }
        $this->message = $r['message'];
        $this->level = $r['level'];
    }

    public function getHistoryProperty()
    {
        return ScanLog::where('user_id', auth()->id())->where('scanned_at', '>=', now()->subHours(12))
            ->with('asset.location')->latest('scanned_at')->limit(10)->get();
    }
}
