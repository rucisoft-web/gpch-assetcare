<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\PmPlans\PmPlanResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Rencana PM yang berlaku untuk aset ini. */
class PmSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'pmSchedules';

    protected static ?string $title = 'Jadwal PM';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('plan.meterParameter', 'openWorkOrder'))
            ->emptyStateHeading('Belum ada rencana PM')->emptyStateDescription('Buat di Pemeliharaan › Rencana PM, per aset atau per kategori.')
            ->columns([
                TextColumn::make('plan.code')->label('Rencana')->fontFamily('mono')->description(fn ($record) => $record->plan?->name),
                TextColumn::make('interval')->label('Interval')->state(fn ($record) => $record->plan?->intervalText()),
                TextColumn::make('last_done_at')->label('Terakhir')->date('d M Y')->placeholder('belum pernah'),
                TextColumn::make('next_due_at')->label('Jatuh tempo')->date('d M Y')->placeholder('—')->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),
                TextColumn::make('next_due_meter')->label('Jatuh tempo meter')->numeric(decimalPlaces: 0)->placeholder('—'),
                TextColumn::make('openWorkOrder.number')->label('WO terbuka')->placeholder('—')
                    ->url(fn ($record) => $record->openWorkOrder ? WorkOrderResource::getUrl('view', ['record' => $record->openWorkOrder]) : null),
            ])
            ->recordActions([Action::make('rencana')->label('Rencana')->url(fn ($record) => PmPlanResource::getUrl('edit', ['record' => $record->pm_plan_id]))
                ->visible(fn () => (bool) auth()->user()?->hasPermission('pm.manage'))]);
    }
}
