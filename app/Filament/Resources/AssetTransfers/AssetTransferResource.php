<?php

namespace App\Filament\Resources\AssetTransfers;

use App\Filament\Concerns\HasPermissions;
use App\Filament\RelationManagers\StatusHistoryRelationManager;
use App\Filament\Support\F;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Department;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Mutasi aset: ajukan → setujui → kirim → terima (scan & kondisi) → selesai; lokasi berubah hanya saat diterima. */
class AssetTransferResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = AssetTransfer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Mutasi aset';

    protected static ?string $pluralModelLabel = 'Mutasi aset';

    protected static ?string $navigationLabel = 'Mutasi aset';

    protected static ?string $viewPermission = 'transfer.request';

    protected static ?string $editPermission = 'transfer.request';

    public const COLORS = ['draft' => 'gray', 'submitted' => 'warning', 'approved' => 'info', 'dispatched' => 'primary', 'received' => 'success', 'closed' => 'gray', 'rejected' => 'danger', 'cancelled' => 'gray'];

    public static function canViewAny(): bool
    {
        return (bool) static::user()?->hasAnyPermission(['transfer.request', 'transfer.approve', 'transfer.receive']);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $u = static::user();
        $n = $u?->hasPermission('transfer.approve') ? AssetTransfer::where('status', 'submitted')->count() : 0;

        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('asset_ids')->label('Aset yang dipindah')->multiple()->required()->searchable()->native(false)->columnSpanFull()
                ->default(fn () => request()->integer('asset') ? [request()->integer('asset')] : [])
                ->getSearchResultsUsing(fn (string $search) => Asset::where('lifecycle_status', 'active')->where('in_transit', false)
                    ->where(fn ($q) => $q->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->with('location')->limit(40)->get()->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name.' · '.$a->location?->labelText()])->all())
                ->getOptionLabelsUsing(fn (array $values) => Asset::with('location')->whereIn('id', $values)->get()
                    ->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name.' · '.$a->location?->labelText()])->all()),
            F::location('to_location_id', 'Lokasi tujuan')->required(),
            Select::make('to_department_id')->label('Departemen tujuan (bila berubah)')->native(false)->options(fn () => Department::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            Textarea::make('reason')->label('Alasan mutasi')->required()->columnSpanFull()->rows(2),
            Toggle::make('submit_now')->label('Langsung ajukan persetujuan')->default(true),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => AssetTransfer::STATUSES[$state] ?? $state)->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextEntry::make('fromLocation.full_path')->label('Dari')->placeholder('beragam'),
                TextEntry::make('toLocation.full_path')->label('Ke'),
                TextEntry::make('toDepartment.name')->label('Departemen tujuan')->placeholder('tidak berubah'),
                TextEntry::make('reason')->label('Alasan')->columnSpan(2),
                TextEntry::make('requester.name')->label('Diajukan'),
                TextEntry::make('approver.name')->label('Disetujui')->placeholder('—'),
                TextEntry::make('dispatched_at')->label('Dikirim')->dateTime('d M Y H:i')->placeholder('—'),
                TextEntry::make('received_at')->label('Diterima')->dateTime('d M Y H:i')->placeholder('—')
                    ->formatStateUsing(fn ($state, $record) => \App\Support\Fmt::dt($state).($record->receiver ? ' · '.$record->receiver->name : '')),
                TextEntry::make('status_reason')->label('Alasan status')->placeholder('—')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Nomor')->weight('bold')->searchable(),
                F::statusColumn('status', AssetTransfer::STATUSES, self::COLORS),
                TextColumn::make('items_count')->counts('items')->label('Aset'),
                TextColumn::make('fromLocation.full_path')->label('Dari')->placeholder('beragam')->wrap(),
                TextColumn::make('toLocation.full_path')->label('Ke')->wrap(),
                TextColumn::make('requester.name')->label('Pengaju'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ItemsRelationManager::class, StatusHistoryRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetTransfers::route('/'),
            'create' => Pages\CreateAssetTransfer::route('/create'),
            'view' => Pages\ViewAssetTransfer::route('/{record}'),
        ];
    }
}
