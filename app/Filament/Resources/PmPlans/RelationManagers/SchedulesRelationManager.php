<?php

namespace App\Filament\Resources\PmPlans\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\F;
use App\Models\PmSchedule;
use App\Services\PlannedMaintenanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $title = 'Jadwal per aset';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $can = fn () => (bool) auth()->user()?->hasPermission('pm.manage');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('asset.location', 'openWorkOrder'))
            ->columns([
                TextColumn::make('asset.asset_code')->label('Aset')->fontFamily('mono')->description(fn ($record) => $record->asset?->name)
                    ->url(fn ($record) => $record->asset ? AssetResource::getUrl('view', ['record' => $record->asset]) : null),
                TextColumn::make('asset.location.full_path')->label('Lokasi')->wrap()->toggleable(),
                TextColumn::make('last_done_at')->label('Terakhir')->date('d M Y')->placeholder('belum pernah'),
                TextColumn::make('next_due_at')->label('Jatuh tempo')->date('d M Y')->sortable()->placeholder('—')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),
                TextColumn::make('next_due_meter')->label('Jatuh tempo meter')->numeric(decimalPlaces: 0)->placeholder('—'),
                TextColumn::make('openWorkOrder.number')->label('WO terbuka')->placeholder('—')
                    ->url(fn ($record) => $record->openWorkOrder ? WorkOrderResource::getUrl('view', ['record' => $record->openWorkOrder]) : null),
                TextColumn::make('done_count')->label('Selesai')->alignEnd(),
            ])
            ->defaultSort('next_due_at')
            ->recordActions([
                Action::make('buatwo')->label('Buat WO sekarang')->icon('heroicon-o-wrench-screwdriver')->visible(fn (PmSchedule $record) => $can() && ! $record->open_work_order_id)
                    ->requiresConfirmation()
                    ->action(fn (PmSchedule $record) => F::attempt(fn () => app(PlannedMaintenanceService::class)->createPmWorkOrder($record, 'dibuat manual', auth()->user()), 'Work order PM dibuat')),
                Action::make('atur')->label('Atur jatuh tempo')->icon('heroicon-o-calendar')->visible($can)
                    ->fillForm(fn (PmSchedule $record) => ['next_due_at' => $record->next_due_at, 'last_done_at' => $record->last_done_at])
                    ->schema([
                        DatePicker::make('last_done_at')->label('Terakhir dikerjakan'),
                        DatePicker::make('next_due_at')->label('Jatuh tempo berikutnya'),
                    ])
                    ->action(fn (PmSchedule $record, array $data) => F::attempt(fn () => $record->update($data), 'Jadwal diperbarui')),
            ]);
    }
}
