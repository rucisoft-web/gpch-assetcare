<?php

namespace App\Filament\Resources\DepreciationClasses;

use App\Filament\Concerns\HasPermissions;
use App\Models\DepreciationClass;
use App\Models\DocumentType;
use App\Models\FiscalGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Kelas aset Kebijakan Penyusutan (bagian 3): umur komersial, residu, kelompok fiskal & dokumen wajib. */
class DepreciationClassResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = DepreciationClass::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset Tetap & Dokumen';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Kelas aset';

    protected static ?string $pluralModelLabel = 'Kelas aset & penyusutan';

    protected static ?string $navigationLabel = 'Kebijakan penyusutan';

    protected static ?string $viewPermission = 'policy.manage';

    protected static ?string $editPermission = 'policy.manage';

    public static function canViewAny(): bool
    {
        return (bool) static::user()?->hasAnyPermission(['policy.manage', 'asset.view_financial', 'document.manage']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('code')->label('Kode')->required()->maxLength(10)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('property_id', app(\App\Support\CurrentProperty::class)->id())),
            TextInput::make('name')->label('Kategori komersial')->required()->columnSpan(2),
            TextInput::make('examples')->label('Contoh aset')->columnSpanFull(),
            Section::make('Buku komersial (SAK EP)')->columns(3)->columnSpanFull()->schema([
                Toggle::make('is_depreciable')->label('Disusutkan')->default(true)->live(),
                TextInput::make('commercial_life_months')->label('Umur manfaat (bulan)')->numeric()->minValue(1)->maxValue(1200)
                    ->required(fn ($get) => $get('is_depreciable'))->visible(fn ($get) => $get('is_depreciable'))
                    ->helperText(fn ($state) => $state ? '= '.round($state / 12, 2).' tahun' : 'mis. 84 = 7 tahun'),
                TextInput::make('residual_pct')->label('Nilai residu (% biaya)')->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
                Toggle::make('is_intangible')->label('Aset tak berwujud (amortisasi)'),
            ]),
            Section::make('Buku fiskal (PMK 72/2023)')->columns(2)->columnSpanFull()->schema([
                Select::make('fiscal_group_code')->label('Kelompok fiskal')->options(fn () => FiscalGroup::options())->native(false)->required(),
                TextInput::make('fiscal_note')->label('Catatan fiskal'),
            ]),
            Section::make('Dokumen wajib (laporan kekurangan, tidak memblokir)')->columnSpanFull()->schema([
                Repeater::make('requirements')->relationship()->hiddenLabel()->columns(3)->defaultItems(0)->addActionLabel('Tambah dokumen wajib')
                    ->schema([
                        Select::make('document_type_id')->label('Jenis dokumen')->options(fn () => DocumentType::options())->searchable()->native(false)->required()->columnSpan(2),
                        Toggle::make('is_mandatory')->label('Wajib')->default(true)->inline(false)->helperText('Mati = dianjurkan (bila ada)'),
                        TextInput::make('condition')->label('Syarat / keterangan')->columnSpanFull(),
                    ]),
            ]),
            TextInput::make('policy_ref')->label('Rujukan bagian kebijakan'),
            TextInput::make('sort')->label('Urutan')->numeric()->default(0),
            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['categories', 'requirements']))
            ->columns([
                TextColumn::make('code')->label('Kode')->badge()->color('primary')->sortable(),
                TextColumn::make('name')->label('Kategori komersial')->description(fn ($record) => $record->examples)->wrap()->searchable(),
                TextColumn::make('commercial_life_months')->label('Umur komersial')->formatStateUsing(fn ($record) => $record->lifeText())->placeholder('Tidak disusutkan'),
                TextColumn::make('fiscal_group_code')->label('Kelompok fiskal')
                    ->formatStateUsing(fn ($state) => ($g = FiscalGroup::where('code', $state)->first()) ? $g->name.($g->life_months ? ' · '.($state === FiscalGroup::BUILDING ? \App\Models\FixedAssetPolicy::current()->building_fiscal_life_years * 12 : $g->life_months) / 12 .' th' : '') : $state)
                    ->description(fn ($record) => $record->fiscal_note)->wrap(),
                TextColumn::make('categories_count')->label('Kategori')->alignEnd(),
                TextColumn::make('requirements_count')->label('Dok. wajib')->alignEnd(),
                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->defaultSort('sort')
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDepreciationClasses::route('/')];
    }
}
