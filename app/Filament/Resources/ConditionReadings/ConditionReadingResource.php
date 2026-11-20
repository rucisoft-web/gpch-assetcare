<?php

namespace App\Filament\Resources\ConditionReadings;

use App\Filament\Concerns\HasPermissions;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\F;
use App\Models\Asset;
use App\Models\ConditionParameter;
use App\Models\ConditionReading;
use App\Services\PlannedMaintenanceService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Log pembacaan kondisi / meter aset (predictive maintenance). */
class ConditionReadingResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = ConditionReading::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|\UnitEnum|null $navigationGroup = 'Pemeliharaan';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'Pembacaan kondisi';

    protected static ?string $pluralModelLabel = 'Pemantauan kondisi';

    protected static ?string $viewPermission = 'wo.view';

    protected static ?string $editPermission = null;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // pembacaan mengikuti properti aset
        return parent::getEloquentQuery()->whereHas('asset');
    }

    /** Form pencatatan; $asset diisi bila dicatat dari kartu aset. */
    public static function readingFields(?Asset $asset = null): array
    {
        return array_values(array_filter([
            $asset ? null : Select::make('asset_id')->label('Aset')->required()->searchable()->native(false)->live()
                ->getSearchResultsUsing(fn (string $search) => Asset::query()->whereIn('lifecycle_status', ['draft', 'active'])
                    ->where(fn ($q) => $q->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->limit(40)->get()->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name])->all())
                ->getOptionLabelUsing(fn ($value) => ($a = Asset::find($value)) ? $a->asset_code.' · '.$a->name : null),
            Select::make('condition_parameter_id')->label('Parameter')->required()->native(false)->live()
                ->options(function (Get $get) use ($asset) {
                    $a = $asset ?? Asset::find($get('asset_id'));

                    return $a ? ConditionParameter::forAsset($a)->mapWithKeys(fn ($p) => [$p->id => $p->name.($p->unit ? ' ('.$p->unit.')' : '')])->all() : [];
                }),
            Placeholder::make('batas')->label('Batas')->content(fn (Get $get) => ConditionParameter::find($get('condition_parameter_id'))?->limitText() ?? '—'),
            TextInput::make('value')->label('Nilai')->numeric()->required(),
            DateTimePicker::make('read_at')->label('Waktu baca')->default(now())->seconds(false),
            TextInput::make('note')->label('Keterangan')->maxLength(255),
        ]));
    }

    public static function record(array $data, ?Asset $asset = null): void
    {
        $asset ??= Asset::findOrFail($data['asset_id']);
        $r = null;
        $ok = F::attempt(function () use ($asset, $data, &$r) {
            $r = app(PlannedMaintenanceService::class)->recordReading($asset, ConditionParameter::findOrFail($data['condition_parameter_id']), (float) $data['value'],
                auth()->user(), $data['note'] ?? null, $data['read_at'] ?? null);
        });
        if ($ok && $r) {
            $n = \Filament\Notifications\Notification::make()->title('Pembacaan tersimpan: '.ConditionReading::STATUSES[$r->status]);
            if ($r->workOrder) {
                $n->body('Work order '.$r->workOrder->number.' '.($r->workOrder->wasRecentlyCreated ? 'dibuat' : 'terkait').' untuk tindak lanjut.')->warning()
                    ->actions([\Filament\Actions\Action::make('wo')->label('Buka WO')->url(WorkOrderResource::getUrl('view', ['record' => $r->workOrder]))]);
            } else {
                $r->status === 'normal' ? $n->success() : $n->warning();
            }
            $n->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('asset', 'parameter', 'recorder', 'workOrder'))
            ->columns([
                TextColumn::make('read_at')->label('Waktu')->dateTime('d M Y H:i', \App\Support\Fmt::tz())->sortable(),
                TextColumn::make('asset.asset_code')->label('Aset')->fontFamily('mono')->description(fn ($record) => $record->asset?->name)
                    ->url(fn ($record) => $record->asset ? AssetResource::getUrl('view', ['record' => $record->asset]) : null)->searchable(),
                TextColumn::make('parameter.name')->label('Parameter'),
                TextColumn::make('value')->label('Nilai')->state(fn ($record) => $record->valueText())->alignEnd(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => ConditionReading::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ConditionReading::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('workOrder.number')->label('WO')->placeholder('—')
                    ->url(fn ($record) => $record->workOrder ? WorkOrderResource::getUrl('view', ['record' => $record->workOrder]) : null),
                TextColumn::make('recorder.name')->label('Dicatat')->toggleable(),
                TextColumn::make('note')->label('Keterangan')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ConditionReading::STATUSES)->multiple(),
                SelectFilter::make('condition_parameter_id')->label('Parameter')->relationship('parameter', 'name'),
                Filter::make('abnormal')->label('Hanya waspada / kritis')->query(fn ($query) => $query->whereIn('status', ['warning', 'critical'])),
            ])
            ->defaultSort('read_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListConditionReadings::route('/')];
    }
}
