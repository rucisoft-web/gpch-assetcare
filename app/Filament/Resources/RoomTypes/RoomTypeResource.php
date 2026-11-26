<?php

namespace App\Filament\Resources\RoomTypes;

use App\Filament\Concerns\HasPermissions;
use App\Models\RoomType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class RoomTypeResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = RoomType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Tipe kamar';

    protected static ?string $pluralModelLabel = 'Tipe kamar';

    protected static ?string $viewPermission = 'reference.manage';

    protected static ?string $editPermission = 'reference.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Kode')->required()->maxLength(20)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $r) => $r->where('property_id', app(\App\Support\CurrentProperty::class)->id())),
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('bed_config')->label('Konfigurasi bed')->placeholder('King / Twin'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable(),
            TextColumn::make('bed_config')->label('Bed'),
            TextColumn::make('rooms_count')->label('Kamar')->counts('rooms'),
            TextColumn::make('templates_count')->label('Template')->counts('templates'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRoomTypes::route('/')];
    }
}
