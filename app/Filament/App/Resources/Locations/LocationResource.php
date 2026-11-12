<?php

namespace App\Filament\App\Resources\Locations;

use App\Filament\App\Resources\Locations\Pages\ManageLocations;
use App\Filament\Concerns\ManagerPermissions;
use App\Models\AssetTemplate;
use App\Models\Location;
use App\Services\AssetIntake;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class LocationResource extends Resource
{
    use ManagerPermissions;

    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Konfigurasi';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Lokasi';

    protected static ?string $pluralModelLabel = 'Lokasi';

    protected static ?string $recordTitleAttribute = 'complete_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama lokasi')->required()->maxLength(255),
            Select::make('parent_id')->label('Lokasi induk')
                ->options(fn (?Location $record) => Location::query()
                    ->when($record, fn ($q) => $q->whereNotIn('id', [$record->id, ...$record->descendantIds()]))
                    ->orderBy('complete_name')->pluck('complete_name', 'id'))
                ->searchable(),
            Select::make('asset_template_id')->label('Jenis ruang (templat aset)')
                ->options(fn () => AssetTemplate::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->helperText('Mis. Kamar Deluxe. Dipakai untuk membangkitkan aset massal sesuai jenis ruang.'),
            Toggle::make('is_active')->label('Aktif')->default(true),
            Textarea::make('note')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('complete_name')
            ->columns([
                TextColumn::make('complete_name')->label('Lokasi')->searchable()->sortable(),
                TextColumn::make('template.name')->label('Jenis ruang')->badge()->color('gray')->placeholder('—')->toggleable(),
                TextColumn::make('assets_count')->label('Jumlah aset')->counts('assets')->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('parent_id')->label('Induk')
                    ->options(fn () => Location::query()->has('children')->orderBy('complete_name')->pluck('complete_name', 'id')),
                SelectFilter::make('asset_template_id')->label('Jenis ruang')
                    ->options(fn () => AssetTemplate::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('set_template')->label('Tetapkan jenis ruang')->icon(Heroicon::OutlinedTag)
                        ->schema([
                            Select::make('asset_template_id')->label('Jenis ruang (templat aset)')->required()
                                ->options(fn () => AssetTemplate::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(['asset_template_id' => $data['asset_template_id']]);
                            Notification::make()->title($records->count().' lokasi diperbarui.')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('generate_assets')->label('Bangkitkan aset sesuai jenis ruang')->icon(Heroicon::OutlinedSparkles)
                        ->requiresConfirmation()
                        ->modalDescription('Aset dibuat berstatus Diharapkan sesuai templat jenis ruang tiap lokasi. Aset yang sudah ada dilewati.')
                        ->action(function (Collection $records) {
                            $count = app(AssetIntake::class)->fromLinkedTemplates($records->pluck('id')->all())->count();
                            Notification::make()->title($count ? "{$count} aset dibangkitkan." : 'Tidak ada aset baru (semua sudah ada atau lokasi tanpa jenis ruang).')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Location $record) => $record->assets()->exists() || $record->children()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLocations::route('/')];
    }
}
