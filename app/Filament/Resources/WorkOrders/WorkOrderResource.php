<?php

namespace App\Filament\Resources\WorkOrders;

use App\Filament\Concerns\HasPermissions;
use App\Filament\RelationManagers\AttachmentsRelationManager;
use App\Filament\RelationManagers\CommentsRelationManager;
use App\Filament\RelationManagers\StatusHistoryRelationManager;
use App\Filament\Support\F;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use App\Services\SlaService;
use App\Support\Fmt;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** M05 Work order: penugasan, eksekusi, bukti, biaya, verifikasi & penutupan dengan status terkontrol. */
class WorkOrderResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = WorkOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Pemeliharaan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Work order';

    protected static ?string $pluralModelLabel = 'Work order';

    protected static ?string $navigationLabel = 'Work order';

    protected static ?string $viewPermission = 'wo.view';

    protected static ?string $editPermission = 'wo.create';

    protected static ?string $recordTitleAttribute = 'number';

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && WorkOrder::visibleTo(static::user())->whereKey($record->getKey())->exists();
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows('wo.create') && in_array($record->status, ['draft', 'reopened'], true);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        $u = static::user();
        if (! $u) {
            return null;
        }
        $n = WorkOrder::visibleTo($u)->whereIn('status', ['assigned', 'in_progress', 'reopened'])
            ->where(fn ($q) => $q->where('pic_id', $u->id)->orWhereJsonContains('team_ids', $u->id))->count();

        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Pekerjaan')->columns(3)->schema([
                TextInput::make('title')->label('Judul pekerjaan')->required()->maxLength(160)->columnSpan(2),
                Select::make('type')->label('Jenis')->options(WorkOrder::TYPES)->default('corrective')->required()->native(false),
                Select::make('priority')->label('Prioritas')->options(ServiceRequest::PRIORITIES)->default('P3')->required()->native(false)
                    ->disabled(fn ($record) => (bool) $record)->helperText(fn ($record) => $record ? 'Ubah prioritas melalui triase laporan.' : null),
                F::asset('asset_id', 'Aset')->default(fn () => request()->integer('asset') ?: null),
                F::location('location_id', 'Lokasi'),
                Textarea::make('description')->label('Uraian')->rows(3)->columnSpanFull(),
                F::money('estimated_cost', 'Estimasi biaya'),
                Toggle::make('needs_vendor')->label('Perlu vendor'),
                Toggle::make('is_safety')->label('Terkait keselamatan'),
            ]),
        ]);
    }

    public static function slaState(WorkOrder $wo): array
    {
        $pct = app(SlaService::class)->percentUsed($wo->received_at, $wo->due_at, $wo->sla_calendar, (int) $wo->hold_minutes, $wo->restored_at ?? ($wo->hold_started_at ?? now()));
        $color = $pct === null ? 'gray' : ($pct >= 100 ? 'danger' : ($pct >= config('assetcare.sla_warning_pct') ? 'warning' : 'success'));

        return [$pct, $color];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Nomor')->searchable()->sortable()->weight('bold'),
                F::priorityColumn(),
                F::statusColumn('status', WorkOrder::STATUSES, WorkOrder::STATUS_COLORS),
                TextColumn::make('title')->label('Pekerjaan')->limit(60)->wrap()->searchable()
                    ->description(fn ($record) => $record->location?->full_path),
                TextColumn::make('asset.asset_code')->label('Aset')->placeholder('—')->searchable()->toggleable(),
                TextColumn::make('pic.name')->label('PIC')->placeholder('belum ditugaskan'),
                TextColumn::make('due_at')->label('Target pulih')->dateTime('d M H:i')->sortable()->placeholder('—')
                    ->color(fn ($record) => $record->isOpen() ? self::slaState($record)[1] : null)
                    ->description(fn ($record) => $record->isOpen() && ($p = self::slaState($record)[0]) !== null ? Fmt::pct($p, 0).' SLA terpakai' : null),
                TextColumn::make('received_at')->label('Diterima')->dateTime('d M H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
                F::moneyColumn('estimated_cost', 'Estimasi')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('priority')->label('Prioritas')->options(ServiceRequest::PRIORITIES),
                SelectFilter::make('status')->options(WorkOrder::STATUSES)->multiple(),
                SelectFilter::make('type')->label('Jenis')->options(WorkOrder::TYPES)->multiple(),
                SelectFilter::make('source')->label('Sumber')->options(WorkOrder::SOURCES),
                SelectFilter::make('pic_id')->label('PIC')->relationship('pic', 'name'),
                Filter::make('dibuat')->label('Tanggal masuk')
                    ->schema([\Filament\Forms\Components\DatePicker::make('from')->label('Masuk dari'), \Filament\Forms\Components\DatePicker::make('until')->label('Masuk sampai')])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->where('received_at', '>=', \Carbon\CarbonImmutable::parse($d, Fmt::tz())->startOfDay()->setTimezone('UTC')))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->where('received_at', '<=', \Carbon\CarbonImmutable::parse($d, Fmt::tz())->endOfDay()->setTimezone('UTC'))))
                    ->indicateUsing(fn (array $data) => array_filter([($data['from'] ?? null) ? 'Masuk dari '.$data['from'] : null, ($data['until'] ?? null) ? 's.d. '.$data['until'] : null])),
                Filter::make('overdue')->label('Melewati target')->query(fn ($query) => $query->whereIn('status', WorkOrder::OPEN)->where('due_at', '<', now())),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('received_at', 'desc')
            ->poll('60s');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CostLinesRelationManager::class,
            RelationManagers\TimeLogsRelationManager::class,
            AttachmentsRelationManager::class,
            RelationManagers\RequestsRelationManager::class,
            StatusHistoryRelationManager::class,
            CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkOrders::route('/'),
            'create' => Pages\CreateWorkOrder::route('/create'),
            'view' => Pages\ViewWorkOrder::route('/{record}'),
            'edit' => Pages\EditWorkOrder::route('/{record}/edit'),
        ];
    }
}
