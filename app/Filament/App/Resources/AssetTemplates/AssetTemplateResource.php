<?php

namespace App\Filament\App\Resources\AssetTemplates;

use App\Enums\LocationType;
use App\Filament\App\Resources\AssetTemplates\Pages\CreateAssetTemplate;
use App\Filament\App\Resources\AssetTemplates\Pages\EditAssetTemplate;
use App\Filament\App\Resources\AssetTemplates\Pages\ListAssetTemplates;
use App\Filament\Concerns\ManagerPermissions;
use App\Models\AssetCategory;
use App\Models\AssetTemplate;
use App\Models\Department;
use App\Models\Location;
use App\Services\AssetIntake;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Templat aset per jenis lokasi (misalnya "Kamar Deluxe"): daftar aset yang diharapkan ada. */
class AssetTemplateResource extends Resource
{
    use ManagerPermissions;

    protected static ?string $model = AssetTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Konfigurasi';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Templat lokasi';

    protected static ?string $pluralModelLabel = 'Templat Ruang / Lokasi';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('name')->label('Nama templat')->required()->maxLength(255),
                Select::make('location_type')->label('Jenis lokasi')->options(LocationType::class)->default(LocationType::Room)->required(),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ]),
            Section::make('Aset yang diharapkan')->columnSpanFull()->schema([
                Repeater::make('lines')->hiddenLabel()->relationship()
                    ->orderColumn('sort')
                    ->columns(6)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah baris aset')
                    ->itemLabel(fn (array $state) => $state['name'] ?? null)
                    ->schema([
                        TextInput::make('name')->label('Nama aset')->required()->columnSpan(2),
                        Select::make('asset_category_id')->label('Kategori')->required()->columnSpan(2)
                            ->options(fn () => AssetCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $set('expected_life_years', AssetCategory::find($state)?->expected_life_years ?? 5)),
                        TextInput::make('quantity')->label('Jumlah')->numeric()->minValue(1)->default(1)->required(),
                        TextInput::make('expected_life_years')->label('Umur (th)')->numeric()->default(5),
                        Select::make('department_id')->label('Departemen')->columnSpan(2)
                            ->options(fn () => Department::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id')),
                        TextInput::make('brand')->label('Merek')->columnSpan(2),
                        TextInput::make('model')->label('Model')->columnSpan(2),
                    ]),
            ]),
            Textarea::make('note')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('location_type')->label('Jenis lokasi')->badge(),
                TextColumn::make('lines_count')->label('Baris aset')->counts('lines'),
                TextColumn::make('lines_sum_quantity')->label('Unit per lokasi')->sum('lines', 'quantity'),
                TextColumn::make('locations_count')->label('Lokasi terhubung')->counts('locations')->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('apply')->label('Terapkan ke lokasi')->icon(Heroicon::OutlinedSparkles)
                        ->modalHeading(fn (AssetTemplate $record) => "Bangkitkan aset dari templat {$record->name}")
                        ->modalSubmitActionLabel('Bangkitkan aset')
                        ->schema([
                            Select::make('location_ids')->label('Lokasi tujuan')->required()->multiple()->searchable()
                                ->options(fn () => Location::query()->where('is_active', true)->orderBy('complete_name')->pluck('complete_name', 'id'))
                                ->default(fn (AssetTemplate $record) => $record->locations()->where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all())
                                ->helperText('Terisi otomatis dengan lokasi yang jenis ruangnya memakai templat ini.'),
                            Toggle::make('skip_existing')->label('Lewati aset yang sudah ada (nama & kategori sama)')->default(true),
                        ])
                        ->action(function (AssetTemplate $record, array $data) {
                            $count = app(AssetIntake::class)->fromTemplate($record, $data['location_ids'], null, (bool) $data['skip_existing'])->count();
                            Notification::make()->title($count ? "{$count} aset dibangkitkan." : 'Tidak ada aset baru (semua sudah ada).')
                                ->body($count ? 'Status: Diharapkan. Verifikasi di lapangan lewat menu Verifikasi Aset.' : null)->success()->send();
                        }),
                    Action::make('duplicate')->label('Duplikasi templat')->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->modalHeading('Duplikasi templat')
                        ->modalDescription('Semua baris aset ikut tersalin. Cocok untuk membuat varian jenis kamar baru.')
                        ->schema([
                            TextInput::make('name')->label('Nama templat baru')->required()->maxLength(255)
                                ->default(fn (AssetTemplate $record) => $record->name.' (salinan)'),
                        ])
                        ->action(function (AssetTemplate $record, array $data) {
                            app(AssetIntake::class)->duplicateTemplate($record, $data['name']);
                            Notification::make()->title('Templat diduplikasi.')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetTemplates::route('/'),
            'create' => CreateAssetTemplate::route('/create'),
            'edit' => EditAssetTemplate::route('/{record}/edit'),
        ];
    }
}
