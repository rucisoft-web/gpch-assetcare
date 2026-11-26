<?php

namespace App\Filament\Resources\ConditionParameters;

use App\Filament\Concerns\HasPermissions;
use App\Models\AssetCategory;
use App\Models\ConditionParameter;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Parameter kondisi & meter (predictive maintenance / PM berbasis meter). */
class ConditionParameterResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = ConditionParameter::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static string|\UnitEnum|null $navigationGroup = 'Pemeliharaan';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'Parameter kondisi';

    protected static ?string $pluralModelLabel = 'Parameter kondisi & meter';

    protected static ?string $viewPermission = 'pm.manage';

    protected static ?string $editPermission = 'pm.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('code')->label('Kode')->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Parameter')->required()->placeholder('mis. Suhu ruang pendingin')->columnSpan(2),
            TextInput::make('unit')->label('Satuan')->placeholder('°C, jam, bar, mm/s, A'),
            Select::make('category_id')->label('Berlaku untuk kategori')->options(fn () => AssetCategory::orderBy('name')->pluck('name', 'id'))->searchable()->placeholder('Semua kategori'),
            Toggle::make('is_meter')->label('Meter kumulatif (jam operasi, km)')->live()->inline(false)
                ->helperText('Dipakai untuk PM berbasis meter; tanpa batas waspada/kritis.'),
            Select::make('direction')->label('Arah bahaya')->options(ConditionParameter::DIRECTIONS)->default('above')->native(false)->hidden(fn (Get $get) => $get('is_meter')),
            TextInput::make('warning_value')->label('Batas waspada')->numeric()->hidden(fn (Get $get) => $get('is_meter')),
            TextInput::make('critical_value')->label('Batas kritis')->numeric()->hidden(fn (Get $get) => $get('is_meter')),
            Select::make('critical_priority')->label('Prioritas WO bila kritis')->options(['P1' => 'P1', 'P2' => 'P2', 'P3' => 'P3', 'P4' => 'P4'])->default('P2')->native(false)->hidden(fn (Get $get) => $get('is_meter')),
            Toggle::make('wo_on_warning')->label('Buat WO sejak batas waspada')->inline(false)->hidden(fn (Get $get) => $get('is_meter')),
            TextInput::make('reading_interval_days')->label('Pembacaan rutin tiap (hari)')->numeric()->minValue(1),
            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            Textarea::make('guidance')->label('Panduan tindakan')->rows(2)->columnSpanFull()->placeholder('mis. cek kompresor, kondensor & seal pintu; pindahkan bahan ke freezer cadangan.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category')->withCount('readings'))
            ->columns([
                TextColumn::make('code')->label('Kode')->fontFamily('mono')->searchable(),
                TextColumn::make('name')->label('Parameter')->searchable()->description(fn ($record) => $record->category?->name ?? 'Semua kategori'),
                TextColumn::make('unit')->label('Satuan'),
                TextColumn::make('limits')->label('Batas')->state(fn ($record) => $record->limitText()),
                IconColumn::make('is_meter')->label('Meter')->boolean(),
                TextColumn::make('readings_count')->label('Pembacaan')->alignEnd(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageConditionParameters::route('/')];
    }
}
