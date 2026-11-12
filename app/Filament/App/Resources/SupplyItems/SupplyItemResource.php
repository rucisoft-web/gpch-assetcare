<?php

namespace App\Filament\App\Resources\SupplyItems;

use App\Enums\StockMovementType;
use App\Enums\SupplyCategory;
use App\Filament\App\Resources\StockMovements\StockMovementResource;
use App\Filament\App\Resources\SupplyItems\Pages\ManageSupplyItems;
use App\Filament\Concerns\OperationalPermissions;
use App\Filament\Support\Domain;
use App\Models\SupplyItem;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupplyItemResource extends Resource
{
    use OperationalPermissions;

    protected static ?string $model = SupplyItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'ATK & Persediaan';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Barang';

    protected static ?string $pluralModelLabel = 'Stok Barang';

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SupplyItem::query()->lowStock()->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Stok di bawah batas minimum';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Kode')->required()->maxLength(40)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', filament()->getTenant()->id)),
            TextInput::make('name')->label('Nama barang')->required()->maxLength(255),
            Select::make('category')->label('Kategori')->options(SupplyCategory::class)->default(SupplyCategory::Stationery)->required(),
            TextInput::make('unit')->label('Satuan')->required()->default('pcs')->datalist(['pcs', 'rim', 'box', 'pak', 'lusin', 'botol', 'galon', 'liter', 'kg', 'roll', 'set']),
            TextInput::make('min_stock')->label('Stok minimum')->numeric()->minValue(0)->default(0)
                ->helperText('Barang masuk daftar "stok menipis" bila stok ≤ angka ini.'),
            TextInput::make('unit_cost')->label('Harga satuan')->numeric()->prefix('Rp')->minValue(0)->default(0),
            TextInput::make('storage_location')->label('Lokasi simpan')->placeholder('Gudang GA, rak B2'),
            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            TextInput::make('opening_stock')->label('Stok awal')->numeric()->minValue(0)->default(0)
                ->visibleOn('create')->helperText('Dicatat sebagai mutasi masuk.'),
        ]);
    }

    public static function stockActions(): array
    {
        return [
            Action::make('stock_in')->label('Stok masuk')->icon(Heroicon::OutlinedArrowDownTray)->color('success')
                ->visible(fn () => static::canOperate())
                ->fillForm(fn (SupplyItem $record) => ['unit_cost' => $record->unit_cost, 'moved_at' => today()])
                ->schema([
                    TextInput::make('quantity')->label(fn (SupplyItem $record) => "Jumlah ({$record->unit})")->numeric()->minValue(0.01)->required(),
                    TextInput::make('unit_cost')->label('Harga satuan')->numeric()->prefix('Rp')->minValue(0),
                    DatePicker::make('moved_at')->label('Tanggal')->required(),
                    TextInput::make('reference')->label('Referensi')->placeholder('No. PO / faktur pemasok'),
                ])
                ->action(fn (SupplyItem $record, array $data) => Domain::attempt(
                    fn () => $record->move(StockMovementType::In, (float) $data['quantity'], collect($data)->except('quantity')->all()),
                    'Stok masuk dicatat.')),
            Action::make('stock_out')->label('Stok keluar')->icon(Heroicon::OutlinedArrowUpTray)->color('warning')
                ->visible(fn () => static::canOperate())
                ->schema([
                    TextInput::make('quantity')->label(fn (SupplyItem $record) => "Jumlah ({$record->unit}), tersedia ".Domain::quantity($record->stock))->numeric()->minValue(0.01)->required(),
                    DatePicker::make('moved_at')->label('Tanggal')->default(today())->required(),
                    TextInput::make('reference')->label('Untuk')->placeholder('Departemen / keperluan')->required(),
                ])
                ->action(fn (SupplyItem $record, array $data) => Domain::attempt(
                    fn () => $record->move(StockMovementType::Out, (float) $data['quantity'], collect($data)->except('quantity')->all()),
                    'Stok keluar dicatat.')),
            Action::make('adjust')->label('Stok opname')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
                ->visible(fn () => static::canManage())
                ->fillForm(fn (SupplyItem $record) => ['counted' => $record->stock])
                ->schema([
                    TextInput::make('counted')->label(fn (SupplyItem $record) => "Hasil hitung fisik ({$record->unit})")->numeric()->minValue(0)->required(),
                    Textarea::make('note')->label('Keterangan')->required(),
                ])
                ->action(function (SupplyItem $record, array $data) {
                    $delta = round((float) $data['counted'] - $record->stock, 2);

                    if ($delta == 0) {
                        return Domain::attempt(fn () => null, 'Stok sudah sesuai, tidak ada penyesuaian.');
                    }

                    return Domain::attempt(fn () => $record->move(StockMovementType::Adjust, $delta, ['note' => $data['note'], 'reference' => 'Stok opname']),
                        'Stok disesuaikan '.($delta > 0 ? '+' : '').Domain::quantity($delta).' '.$record->unit.'.');
                }),
            Action::make('history')->label('Riwayat mutasi')->icon(Heroicon::OutlinedClock)->color('gray')
                ->url(fn (SupplyItem $record) => StockMovementResource::getUrl('index', ['filters' => ['supply_item_id' => ['value' => $record->id]]])),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Barang')->searchable(['name', 'code'])->weight('bold')
                    ->description(fn (SupplyItem $record) => $record->code),
                TextColumn::make('category')->label('Kategori')->badge(),
                TextColumn::make('stock')->label('Stok')->sortable()
                    ->formatStateUsing(fn (SupplyItem $record) => Domain::quantity($record->stock).' '.$record->unit)
                    ->color(fn (SupplyItem $record) => $record->isLow() ? 'danger' : null)
                    ->weight(fn (SupplyItem $record) => $record->isLow() ? 'bold' : null)
                    ->description(fn (SupplyItem $record) => $record->isLow() ? 'Menipis · min '.Domain::quantity($record->min_stock) : 'min '.Domain::quantity($record->min_stock)),
                TextColumn::make('unit_cost')->label('Harga satuan')->formatStateUsing(fn ($state) => Money::format($state))->toggleable(),
                TextColumn::make('value')->label('Nilai stok')
                    ->state(fn (SupplyItem $record) => Money::format($record->stock * (float) $record->unit_cost))->toggleable(),
                TextColumn::make('storage_location')->label('Lokasi')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('low')->label('Stok menipis')->query(fn (Builder $query) => $query->lowStock()),
                SelectFilter::make('category')->label('Kategori')->options(SupplyCategory::class),
                TernaryFilter::make('is_active')->label('Aktif')->default(true),
            ])
            ->recordActions([...array_slice(static::stockActions(), 0, 2), ActionGroup::make([...array_slice(static::stockActions(), 2), EditAction::make(), DeleteAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSupplyItems::route('/')];
    }
}
