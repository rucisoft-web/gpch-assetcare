<?php

namespace App\Filament\Pages;

use App\Services\ExecutiveDashboard;
use Filament\Pages\Page;

/** Dashboard pimpinan: grafik kondisi aset, pemeliharaan, biaya & dokumen; setiap grafik dapat diklik sampai ke data rinci. */
class DashboardPimpinan extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Umum';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Dashboard pimpinan';

    protected static ?string $title = 'Dashboard pimpinan';

    protected static ?string $slug = 'dashboard-pimpinan';

    protected string $view = 'filament.pages.dashboard-pimpinan';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('dashboard.view');
    }

    protected function getViewData(): array
    {
        return ['d' => app(ExecutiveDashboard::class)->build(auth()->user()->canSeeFinancials())];
    }
}
