<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\F;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Riwayat pemeliharaan aset: insidental (korektif/darurat), preventive, inspeksi, predictive. */
class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';

    protected static ?string $title = 'Riwayat pemeliharaan';

    public const GROUPS = ['insidental' => ['corrective', 'emergency'], 'terencana' => ['preventive', 'inspection'], 'predictive' => ['predictive'], 'lainnya' => ['improvement']];

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord->workOrders()->whereIn('status', WorkOrder::OPEN)->count();

        return $n ? (string) $n : null;
    }

    public function table(Table $table): Table
    {
        $fin = (bool) auth()->user()?->canSeeFinancials();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('costLines', 'pmSchedule.plan'))
            ->description(function () {
                $a = $this->getOwnerRecord();
                $done = $a->workOrders()->whereNotNull('completed_at');
                $last = (clone $done)->whereIn('type', ['preventive', 'inspection'])->max('completed_at');

                return 'Insidental '.(clone $done)->whereIn('type', self::GROUPS['insidental'])->count()
                    .' · Preventive/inspeksi '.(clone $done)->whereIn('type', self::GROUPS['terencana'])->count()
                    .' · Predictive '.(clone $done)->where('type', 'predictive')->count()
                    .($last ? ' · PM terakhir '.\Carbon\Carbon::parse($last)->timezone(\App\Support\Fmt::tz())->format('d M Y') : '');
            })
            ->columns(array_values(array_filter([
                TextColumn::make('number')->label('Nomor')->fontFamily('mono'),
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn ($state) => WorkOrder::TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'corrective', 'emergency' => 'danger', 'preventive', 'inspection' => 'success', 'predictive' => 'warning', default => 'gray' })
                    ->description(fn ($record) => $record->pmSchedule?->plan?->code ?? (WorkOrder::SOURCES[$record->source] ?? null)),
                F::priorityColumn(),
                TextColumn::make('title')->label('Pekerjaan')->wrap()->description(fn ($record) => $record->action_taken ? \Illuminate\Support\Str::limit($record->action_taken, 90) : null),
                TextColumn::make('failure_mode')->label('Mode kegagalan')->placeholder('—')->toggleable(),
                F::statusColumn('status', WorkOrder::STATUSES, WorkOrder::STATUS_COLORS),
                TextColumn::make('received_at')->label('Diterima')->dateTime('d M Y H:i', \App\Support\Fmt::tz())->sortable(),
                TextColumn::make('completed_at')->label('Selesai')->dateTime('d M Y', \App\Support\Fmt::tz())->placeholder('—'),
                $fin ? TextColumn::make('biaya')->label('Biaya')->state(fn ($record) => $record->totalCost())->money('IDR', locale: 'id')->alignEnd() : null,
            ])))
            ->filters([
                SelectFilter::make('kelompok')->label('Kelompok')->options(['insidental' => 'Insidental (korektif & darurat)', 'terencana' => 'Preventive & inspeksi', 'predictive' => 'Predictive', 'lainnya' => 'Penyempurnaan'])
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null) ? $query->whereIn('type', self::GROUPS[$data['value']]) : $query),
                SelectFilter::make('status')->options(WorkOrder::STATUSES)->multiple(),
            ])
            ->defaultSort('received_at', 'desc')
            ->recordActions([Action::make('buka')->label('Buka')->url(fn ($record) => WorkOrderResource::getUrl('view', ['record' => $record]))]);
    }
}
