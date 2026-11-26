<?php

namespace App\Filament\Resources\AssetQuantityGroups;

use App\Filament\Concerns\HasPermissions;
use App\Filament\Support\F;
use App\Models\AssetCategory;
use App\Models\AssetQuantityGroup;
use App\Support\Fmt;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Kelompok jumlah (gelas, linen, kursi sejenis): satu QR per kelompok, hitung aktual tercatat. */
class AssetQuantityGroupResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = AssetQuantityGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Kelompok jumlah';

    protected static ?string $pluralModelLabel = 'Kelompok jumlah';

    protected static ?string $navigationLabel = 'Kelompok jumlah';

    protected static ?string $viewPermission = 'asset.view';

    protected static ?string $editPermission = 'asset.edit';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('name')->label('Nama kelompok')->required()->columnSpan(2),
            Select::make('category_id')->label('Kategori')->required()->native(false)->searchable()
                ->options(fn () => AssetCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            F::location('location_id', 'Lokasi')->required(),
            TextInput::make('unit')->label('Satuan')->default('pcs')->required(),
            TextInput::make('expected_qty')->label('Jumlah standar')->numeric()->minValue(0),
            Select::make('status')->options(AssetQuantityGroup::STATUSES)->default('active')->native(false),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('group_code')->label('Kode')->weight('bold'),
                TextEntry::make('name')->label('Nama'),
                TextEntry::make('location.full_path')->label('Lokasi'),
                TextEntry::make('category.name')->label('Kategori'),
                TextEntry::make('expected_qty')->label('Standar')->formatStateUsing(fn ($state, $record) => Fmt::num($state).' '.$record->unit)->placeholder('—'),
                TextEntry::make('on_hand')->label('Hitung terakhir')->formatStateUsing(fn ($state, $record) => Fmt::num($state).' '.$record->unit)->placeholder('belum dihitung'),
                TextEntry::make('selisih')->label('Selisih')->state(fn ($record) => $record->on_hand === null || $record->expected_qty === null ? null : Fmt::num((float) $record->on_hand - (float) $record->expected_qty))->placeholder('—'),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => AssetQuantityGroup::STATUSES[$state] ?? $state),
            ]),
        ]);
    }

    public static function countAction(): Action
    {
        return Action::make('hitung')->label('Catat hitung')->icon('heroicon-o-calculator')->color('primary')
            ->visible(fn () => auth()->user()->hasAnyPermission(['asset.edit', 'asset.verify']))
            ->schema([
                TextInput::make('qty')->label('Jumlah aktual')->numeric()->minValue(0)->required(),
                TextInput::make('reason')->label('Catatan (wajib bila selisih)'),
            ])
            ->action(fn (AssetQuantityGroup $record, array $data) => F::attempt(function () use ($record, $data) {
                if ($record->expected_qty !== null && (float) $data['qty'] !== (float) $record->expected_qty && blank($data['reason'] ?? null)) {
                    throw \App\Services\WorkflowException::invalid('Jumlah berbeda dari standar; isi catatan selisih.');
                }
                $record->recordCount((float) $data['qty'], $data['reason'] ?? null);
            }, 'Hitung tercatat'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group_code')->label('Kode')->weight('bold')->searchable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('location.full_path')->label('Lokasi')->wrap(),
                TextColumn::make('expected_qty')->label('Standar')->formatStateUsing(fn ($state) => Fmt::num($state)),
                TextColumn::make('on_hand')->label('Hitung')->formatStateUsing(fn ($state) => Fmt::num($state))->placeholder('—')
                    ->color(fn ($record) => $record->on_hand !== null && $record->expected_qty !== null && (float) $record->on_hand < (float) $record->expected_qty ? 'danger' : null),
                F::statusColumn('status', AssetQuantityGroup::STATUSES, ['draft' => 'warning', 'active' => 'success', 'retired' => 'gray']),
            ])
            ->recordActions([ViewAction::make(), static::countAction(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetQuantityGroups::route('/'),
            'create' => Pages\CreateAssetQuantityGroup::route('/create'),
            'view' => Pages\ViewAssetQuantityGroup::route('/{record}'),
            'edit' => Pages\EditAssetQuantityGroup::route('/{record}/edit'),
        ];
    }
}
