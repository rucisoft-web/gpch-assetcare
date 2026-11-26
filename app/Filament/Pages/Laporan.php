<?php

namespace App\Filament\Pages;

use App\Services\DashboardService;
use App\Services\ReportService;
use Filament\Pages\Page;

/** M08 Laporan & KPI: ringkasan periode + tabel yang dapat diunduh CSV. */
class Laporan extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Laporan & KPI';

    protected static ?string $title = 'Laporan & KPI';

    protected string $view = 'filament.pages.laporan';

    public string $report = 'backlog';

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('report.view');
    }

    public function mount(): void
    {
        $tz = config('assetcare.display_timezone');
        $this->from = now($tz)->subDays(29)->toDateString();
        $this->to = now($tz)->toDateString();
    }

    public function setReport(string $key): void
    {
        if (array_key_exists($key, app(ReportService::class)->available(auth()->user()))) {
            $this->report = $key;
        }
    }

    protected function getViewData(): array
    {
        $u = auth()->user();
        $svc = app(ReportService::class);
        $tz = config('assetcare.display_timezone');

        return [
            'reports' => $svc->available($u),
            'data' => $svc->build($this->report, $u, $this->from, $this->to),
            'kpi' => app(DashboardService::class)->kpis(\Carbon\CarbonImmutable::parse($this->from, $tz)->startOfDay()->utc(), \Carbon\CarbonImmutable::parse($this->to, $tz)->endOfDay()->utc(), $u->canSeeFinancials()),
            'canExport' => $u->hasPermission('report.export'),
        ];
    }
}
