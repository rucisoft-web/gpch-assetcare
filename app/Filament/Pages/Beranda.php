<?php

namespace App\Filament\Pages;

use App\Services\DashboardService;
use App\Support\CurrentProperty;
use Filament\Pages\Dashboard;

/** Beranda sesuai peran: pekerjaan hari ini, overdue, risiko & persetujuan tertunda. */
class Beranda extends Dashboard
{
    protected static ?string $title = 'Beranda';

    protected static ?string $navigationLabel = 'Beranda';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static string|\UnitEnum|null $navigationGroup = 'Umum';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.beranda';

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    public function getTitle(): string
    {
        return 'Beranda';
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $svc = app(DashboardService::class);
        $canDash = $user->hasPermission('dashboard.view');

        return [
            'user' => $user,
            'property' => app(CurrentProperty::class)->get(),
            'roles' => $user->activeRoles()->pluck('name')->join(', '),
            'tasks' => $svc->myTasks($user),
            'kpi' => $canDash ? $svc->kpis(financial: $user->canSeeFinancials()) : null,
        ];
    }
}
