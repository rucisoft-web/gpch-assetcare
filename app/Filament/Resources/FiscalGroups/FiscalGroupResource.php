<?php

namespace App\Filament\Resources\FiscalGroups;

use App\Filament\Concerns\HasPermissions;
use App\Models\FiscalGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Kelompok harta fiskal (UU PPh Pasal 11, PMK 72/2023); umur bangunan permanen diatur di kebijakan. */
class FiscalGroupResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = FiscalGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset Tetap & Dokumen';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Kelompok fiskal';

    protected static ?string $pluralModelLabel = 'Kelompok fiskal';

    protected static ?string $viewPermission = 'policy.manage';

    protected static ?string $editPermission = 'policy.manage';

    public static function canCreate(): bool
    {
        return false; // kelompok ditetapkan undang-undang; hanya umur & catatan yang dapat dikoreksi
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Kode')->disabled(),
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('life_months')->label('Masa manfaat (bulan)')->numeric()->minValue(1)
                ->helperText(fn ($record) => $record?->code === FiscalGroup::BUILDING ? 'Bangunan permanen memakai umur di "Batas kapitalisasi & umur fiskal".' : 'Kosong = tidak disusutkan'),
            TextInput::make('note')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Kode')->badge()->color('gray'),
            TextColumn::make('name')->label('Kelompok'),
            TextColumn::make('life_months')->label('Masa manfaat')->formatStateUsing(fn ($state, $record) => $record->code === FiscalGroup::BUILDING
                ? \App\Models\FixedAssetPolicy::current()->building_fiscal_life_years.' tahun (kebijakan)' : ($state / 12).' tahun')->placeholder('Tidak disusutkan'),
            TextColumn::make('rate')->label('Tarif garis lurus')->state(fn ($record) => $record->code === FiscalGroup::BUILDING
                ? round(100 / \App\Models\FixedAssetPolicy::current()->building_fiscal_life_years, 2) : $record->rate)->suffix('%')->placeholder('—'),
            TextColumn::make('note')->label('Catatan')->wrap(),
        ])->defaultSort('sort')->paginated(false)->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFiscalGroups::route('/')];
    }
}
