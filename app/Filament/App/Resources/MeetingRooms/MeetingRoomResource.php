<?php

namespace App\Filament\App\Resources\MeetingRooms;

use App\Filament\App\Resources\MeetingRooms\Pages\ManageMeetingRooms;
use App\Filament\Concerns\ManagerPermissions;
use App\Models\Location;
use App\Models\MeetingRoom;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class MeetingRoomResource extends Resource
{
    use ManagerPermissions;

    protected static ?string $model = MeetingRoom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Konfigurasi';

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'Ruang rapat';

    protected static ?string $pluralModelLabel = 'Ruang Rapat';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama ruang')->required()->maxLength(255),
            TextInput::make('capacity')->label('Kapasitas (orang)')->numeric()->minValue(1)->default(10)->required(),
            Select::make('location_id')->label('Lokasi')->searchable()
                ->options(fn () => Location::query()->where('is_active', true)->orderBy('complete_name')->pluck('complete_name', 'id')),
            Toggle::make('is_active')->label('Bisa dipesan')->default(true)->inline(false),
            Textarea::make('facilities')->label('Fasilitas')->placeholder('Proyektor, layar, video conference, papan tulis')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Ruang')->searchable()->weight('bold'),
                TextColumn::make('capacity')->label('Kapasitas')->suffix(' orang')->sortable(),
                TextColumn::make('location.complete_name')->label('Lokasi')->placeholder('—'),
                TextColumn::make('facilities')->label('Fasilitas')->limit(60)->placeholder('—'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMeetingRooms::route('/')];
    }
}
