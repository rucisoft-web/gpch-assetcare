<?php

namespace App\Filament\Resources\Locations;

use App\Filament\Concerns\HasPermissions;
use App\Models\Location;
use App\Models\RoomType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Struktur lokasi: properti → gedung/zona → lantai → ruang/kamar → titik pemasangan. Kode unik per properti. */
class LocationResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = Location::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Lokasi';

    protected static ?string $pluralModelLabel = 'Lokasi & kamar';

    protected static ?string $viewPermission = 'reference.manage';

    protected static ?string $editPermission = 'reference.manage';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Lokasi')->columns(3)->schema([
                TextInput::make('code')->label('Kode lokasi')->required()->maxLength(40)->helperText('Unik dalam properti, mis. GU-L3-301')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $r) => $r->where('property_id', app(\App\Support\CurrentProperty::class)->id())),
                TextInput::make('name')->label('Nama')->required(),
                Select::make('location_type')->label('Jenis')->options(Location::TYPES)->required()->native(false)->live(),
                Select::make('parent_id')->label('Induk')->native(false)->searchable()
                    ->options(fn ($record) => collect(Location::options(['building', 'zone', 'floor', 'area', 'room', 'venue']))->except($record?->id)->all()),
                Select::make('room_type_id')->label('Tipe kamar')->options(fn () => RoomType::where('is_active', true)->pluck('name', 'id'))->native(false)
                    ->visible(fn (Get $get) => in_array($get('location_type'), ['room', 'venue'], true)),
                TextInput::make('room_ref')->label('Nomor kamar (PMS)')->maxLength(20)
                    ->visible(fn (Get $get) => in_array($get('location_type'), ['room', 'venue'], true)),
                TextInput::make('floor_label')->label('Label lantai')->maxLength(20)->visible(fn (Get $get) => $get('location_type') === 'floor'),
                Select::make('pms_room_status')->label('Status kamar (informasi PMS)')->options(Location::PMS_STATUSES)->native(false)
                    ->helperText('Terpisah dari status aset; Asset Care tidak mengubah PMS.')
                    ->visible(fn (Get $get) => $get('location_type') === 'room'),
                Toggle::make('is_active')->label('Aktif')->default(true)->helperText('Lokasi yang sudah bertransaksi dinonaktifkan, tidak dihapus.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(query: fn ($query, $direction) => $query->orderBy('sort_key', $direction)),
                TextColumn::make('name')->label('Nama')->searchable()->description(fn ($record) => $record->parent?->full_path),
                TextColumn::make('location_type')->label('Jenis')->badge()->color('gray')->formatStateUsing(fn ($state) => Location::TYPES[$state] ?? $state),
                TextColumn::make('roomType.name')->label('Tipe kamar')->placeholder('—'),
                TextColumn::make('assets_count')->label('Aset')->counts('assets'),
                TextColumn::make('pms_room_status')->label('Status PMS')->formatStateUsing(fn ($state) => Location::PMS_STATUSES[$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('location_type')->label('Jenis')->options(Location::TYPES),
                SelectFilter::make('room_type_id')->label('Tipe kamar')->relationship('roomType', 'name'),
                SelectFilter::make('parent_id')->label('Induk')->options(fn () => Location::options(['building', 'zone', 'floor'])),
            ])
            ->defaultSort('sort_key')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SlotsRelationManager::class,
            RelationManagers\ExceptionsRelationManager::class,
            RelationManagers\AssetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
