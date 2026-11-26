<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\ConditionReadings\ConditionReadingResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\ConditionReading;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Pembacaan kondisi / meter aset (predictive maintenance). */
class ConditionRelationManager extends RelationManager
{
    protected static string $relationship = 'conditionReadings';

    protected static ?string $title = 'Kondisi & meter';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord->conditionReadings()->where('status', 'critical')->where('read_at', '>=', now()->subDays(30))->count();

        return $n ? (string) $n : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $asset = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parameter', 'recorder', 'workOrder'))
            ->description(function () use ($asset) {
                $latest = $asset->conditionReadings()->with('parameter')->latest('read_at')->get()->unique('condition_parameter_id')->take(6);

                return $latest->isEmpty() ? 'Belum ada pembacaan.' : 'Terakhir: '.$latest->map(fn ($r) => $r->parameter?->name.' '.$r->valueText().($r->status !== 'normal' ? ' ('.ConditionReading::STATUSES[$r->status].')' : ''))->join(' · ');
            })
            ->columns([
                TextColumn::make('read_at')->label('Waktu')->dateTime('d M Y H:i', \App\Support\Fmt::tz())->sortable(),
                TextColumn::make('parameter.name')->label('Parameter'),
                TextColumn::make('value')->label('Nilai')->state(fn ($record) => $record->valueText())->alignEnd(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => ConditionReading::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ConditionReading::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('workOrder.number')->label('WO')->placeholder('—')
                    ->url(fn ($record) => $record->workOrder ? WorkOrderResource::getUrl('view', ['record' => $record->workOrder]) : null),
                TextColumn::make('recorder.name')->label('Dicatat'),
                TextColumn::make('note')->label('Keterangan')->toggleable(),
            ])
            ->filters([SelectFilter::make('condition_parameter_id')->label('Parameter')->relationship('parameter', 'name')])
            ->defaultSort('read_at', 'desc')
            ->headerActions([
                Action::make('catat')->label('Catat pembacaan')->icon('heroicon-o-pencil-square')
                    ->visible(fn () => (bool) auth()->user()?->hasPermission('condition.record'))
                    ->schema(ConditionReadingResource::readingFields($asset))
                    ->action(fn (array $data) => ConditionReadingResource::record($data, $asset)),
            ]);
    }
}
