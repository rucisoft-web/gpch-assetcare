<?php

namespace App\Filament\App\Resources\StockMovements;

use App\Enums\StockMovementType;
use App\Filament\App\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Concerns\InteractsWithTenant;
use App\Filament\Support\Domain;
use App\Models\StockMovement;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Kartu stok: semua mutasi barang, hanya-baca. */
class StockMovementResource extends Resource
{
    use InteractsWithTenant;

    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'ATK & Persediaan';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Mutasi stok';

    protected static ?string $pluralModelLabel = 'Mutasi Stok';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('moved_at')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('item.name')->label('Barang')->searchable()->description(fn (StockMovement $record) => $record->item?->code),
                TextColumn::make('type')->label('Jenis')->badge(),
                TextColumn::make('quantity')->label('Jumlah')
                    ->formatStateUsing(fn (StockMovement $record) => match ($record->type) {
                        StockMovementType::Out => '−'.Domain::quantity($record->quantity),
                        StockMovementType::In => '+'.Domain::quantity($record->quantity),
                        default => ($record->quantity > 0 ? '+' : '−').Domain::quantity(abs($record->quantity)),
                    }.' '.$record->item?->unit)
                    ->color(fn (StockMovement $record) => $record->type === StockMovementType::Out || $record->quantity < 0 ? 'warning' : 'success'),
                TextColumn::make('stock_after')->label('Saldo')->formatStateUsing(fn ($state) => Domain::quantity($state)),
                TextColumn::make('unit_cost')->label('Harga')->formatStateUsing(fn ($state) => $state ? Money::format($state) : '—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference')->label('Referensi')->searchable()->placeholder('—')->description(fn (StockMovement $record) => $record->note),
                TextColumn::make('user.name')->label('Oleh')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('supply_item_id')->label('Barang')->relationship('item', 'name')->searchable(),
                SelectFilter::make('type')->label('Jenis')->options(StockMovementType::class),
                Filter::make('period')->label('Periode')
                    ->schema([DatePicker::make('from')->label('Dari tanggal'), DatePicker::make('until')->label('Sampai tanggal')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('moved_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('moved_at', '<=', $d))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }
}
