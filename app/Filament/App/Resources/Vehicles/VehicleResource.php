<?php

namespace App\Filament\App\Resources\Vehicles;

use App\Enums\VehicleBookingState;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\App\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\App\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\App\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\App\Resources\Vehicles\Pages\ViewVehicle;
use App\Filament\App\Resources\Vehicles\RelationManagers\BookingsRelationManager;
use App\Filament\App\Resources\Vehicles\RelationManagers\DocumentsRelationManager;
use App\Filament\App\Resources\Vehicles\RelationManagers\FuelLogsRelationManager;
use App\Filament\App\Resources\Vehicles\RelationManagers\ServicesRelationManager;
use App\Filament\Concerns\OperationalPermissions;
use App\Filament\Forms\AssetSelect;
use App\Models\Vehicle;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VehicleResource extends Resource
{
    use OperationalPermissions;

    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Layanan Umum';

    protected static ?int $navigationSort = 21;

    protected static ?string $modelLabel = 'Kendaraan';

    protected static ?string $pluralModelLabel = 'Kendaraan Dinas';

    protected static ?string $recordTitleAttribute = 'plate_number';

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
        $count = Vehicle::query()->dueBefore(today()->addDays(30))->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pajak, STNK, KIR, asuransi, atau servis jatuh tempo ≤ 30 hari';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas')->columns(4)->columnSpanFull()->schema([
                TextInput::make('plate_number')->label('Nomor polisi')->required()->maxLength(20)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', filament()->getTenant()->id)),
                TextInput::make('name')->label('Merek / model')->required()->maxLength(255)->columnSpan(2),
                Select::make('type')->label('Jenis')->options(VehicleType::class)->default(VehicleType::Car)->required(),
                TextInput::make('year')->label('Tahun')->numeric()->minValue(1980)->maxValue(2100),
                TextInput::make('fuel_type')->label('Bahan bakar')->datalist(['Pertalite', 'Pertamax', 'Pertamax Turbo', 'Dexlite', 'Pertamina Dex', 'Solar', 'Listrik']),
                TextInput::make('seats')->label('Kursi')->numeric()->minValue(1),
                TextInput::make('odometer')->label('Odometer (km)')->numeric()->minValue(0)->default(0),
                Select::make('status')->label('Status')->options(VehicleStatus::class)->default(VehicleStatus::Available)->required(),
                TextInput::make('default_driver')->label('Pengemudi tetap')->maxLength(255),
                AssetSelect::make('asset_id', required: false)->label('Tercatat sebagai aset')->columnSpan(2),
            ]),
            Section::make('Jatuh tempo')->description('Tanggal ini muncul di dasbor dan menu sebagai pengingat 30 hari sebelumnya.')->columns(3)->columnSpanFull()->schema([
                DatePicker::make('tax_due_date')->label('Pajak tahunan (PKB)'),
                DatePicker::make('stnk_due_date')->label('Perpanjangan STNK (5 tahunan)'),
                DatePicker::make('kir_due_date')->label('Uji KIR'),
                DatePicker::make('insurance_due_date')->label('Asuransi berakhir'),
                DatePicker::make('next_service_date')->label('Servis berikutnya'),
                TextInput::make('next_service_km')->label('Servis berikutnya di km')->numeric(),
            ]),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function dueColor(?CarbonInterface $date): ?string
    {
        return match (true) {
            $date === null => null,
            $date->lt(today()) => 'danger',
            $date->lte(today()->addDays(30)) => 'warning',
            default => null,
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        $due = fn (string $column, string $label) => TextEntry::make($column)->label($label)->date('d/m/Y')->placeholder('—')
            ->color(fn (Vehicle $record) => static::dueColor($record->{$column}))
            ->weight(fn (Vehicle $record) => static::dueColor($record->{$column}) ? 'bold' : null);

        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('plate_number')->label('Nomor polisi')->weight('bold'),
                TextEntry::make('name')->label('Merek / model'),
                TextEntry::make('type')->label('Jenis')->badge(),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('year')->label('Tahun')->placeholder('—'),
                TextEntry::make('fuel_type')->label('Bahan bakar')->placeholder('—'),
                TextEntry::make('odometer')->label('Odometer')->numeric(thousandsSeparator: '.')->suffix(' km'),
                TextEntry::make('default_driver')->label('Pengemudi tetap')->placeholder('—'),
                TextEntry::make('asset.code')->label('Aset')->placeholder('—')
                    ->url(fn (Vehicle $record) => $record->asset_id ? AssetResource::getUrl('view', ['record' => $record->asset_id]) : null),
                TextEntry::make('notes')->label('Catatan')->placeholder('—')->columnSpan(3),
            ]),
            Section::make('Jatuh tempo')->columns(6)->columnSpanFull()->schema([
                $due('tax_due_date', 'Pajak tahunan'),
                $due('stnk_due_date', 'STNK'),
                $due('kir_due_date', 'Uji KIR'),
                $due('insurance_due_date', 'Asuransi'),
                $due('next_service_date', 'Servis berikutnya'),
                TextEntry::make('next_service_km')->label('Servis di km')->numeric(thousandsSeparator: '.')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('plate_number')
            ->columns([
                TextColumn::make('plate_number')->label('Nomor polisi')->searchable()->weight('bold')
                    ->description(fn (Vehicle $record) => $record->name),
                TextColumn::make('type')->label('Jenis')->badge(),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (Vehicle $record) => $record->bookings()->where('state', VehicleBookingState::InUse->value)->exists() ? 'Sedang dipakai' : null),
                TextColumn::make('odometer')->label('Odometer')->numeric(thousandsSeparator: '.')->suffix(' km')->sortable(),
                TextColumn::make('next_due')->label('Jatuh tempo terdekat')
                    ->state(fn (Vehicle $record) => ($item = $record->dueItems(today()->addYears(10))->first()) ? $item['label'].' · '.$item['date']->format('d/m/Y') : '—')
                    ->color(fn (Vehicle $record) => static::dueColor($record->dueItems(today()->addYears(10))->first()['date'] ?? null)),
                TextColumn::make('default_driver')->label('Pengemudi')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(VehicleStatus::class),
                SelectFilter::make('type')->label('Jenis')->options(VehicleType::class),
                Filter::make('due_soon')->label('Jatuh tempo ≤ 30 hari')->query(fn (Builder $query) => $query->dueBefore(today()->addDays(30))),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [BookingsRelationManager::class, FuelLogsRelationManager::class, ServicesRelationManager::class, DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'view' => ViewVehicle::route('/{record}'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
