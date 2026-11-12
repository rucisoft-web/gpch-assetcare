<?php

namespace App\Filament\App\Resources\VehicleBookings;

use App\Enums\VehicleBookingState;
use App\Enums\VehicleStatus;
use App\Filament\App\Resources\VehicleBookings\Pages\ManageVehicleBookings;
use App\Filament\App\Resources\Vehicles\VehicleResource;
use App\Filament\Concerns\OperationalPermissions;
use App\Filament\Support\Domain;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use BackedEnum;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VehicleBookingResource extends Resource
{
    use OperationalPermissions;

    protected static ?string $model = VehicleBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Layanan Umum';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Pemesanan kendaraan';

    protected static ?string $pluralModelLabel = 'Pemesanan Kendaraan';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $count = static::canManage() ? VehicleBooking::where('state', VehicleBookingState::Requested)->count() : 0;

        return $count ?: null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Menunggu persetujuan';
    }

    public static function canEdit(Model $record): bool
    {
        return match ($record->state) {
            VehicleBookingState::Requested => static::canOperate(),
            VehicleBookingState::Approved => static::canManage(),
            default => false,
        };
    }

    /** @return array<int, string> */
    public static function vehicleOptions(): array
    {
        return Vehicle::query()->where('status', VehicleStatus::Available)->orderBy('plate_number')->get()
            ->mapWithKeys(fn (Vehicle $v) => [$v->id => "{$v->plate_number} · {$v->name}".($v->seats ? " · {$v->seats} kursi" : '')])
            ->all();
    }

    /** Aturan validasi: kendaraan tidak sedang dipegang pemesanan lain pada jam itu. */
    public static function availabilityRule(string $vehicleField = 'vehicle_id'): Closure
    {
        return fn (Get $get, $livewire, ?VehicleBooking $record = null) => function (string $attribute, $value, Closure $fail) use ($get, $record, $vehicleField) {
            $vehicleId = (int) $get($vehicleField);
            $start = $get('starts_at') ?? $record?->starts_at;
            $end = $get('ends_at') ?? $record?->ends_at;
            $conflict = VehicleBooking::conflictFor($vehicleId, $start ? Carbon::parse($start) : null, $end ? Carbon::parse($end) : null, $record?->id);

            if ($conflict) {
                $fail(VehicleBooking::conflictMessage($conflict));
            }
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('purpose')->label('Keperluan')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('destination')->label('Tujuan')->required()->maxLength(255),
            TextInput::make('passengers')->label('Jumlah penumpang')->numeric()->minValue(1)->default(1)->required(),
            DateTimePicker::make('starts_at')->label('Berangkat')->required()->seconds(false)->minutesStep(15)
                ->default(now()->addHour()->startOfHour()),
            DateTimePicker::make('ends_at')->label('Kembali')->required()->seconds(false)->minutesStep(15)->after('starts_at')
                ->default(now()->addHours(4)->startOfHour()),
            Select::make('department_id')->label('Departemen')->options(fn () => Domain::departmentOptions())->searchable(),
            Select::make('requester_id')->label('Pemohon')->options(fn () => static::memberOptions())->searchable()->default(fn () => auth()->id()),
            Select::make('vehicle_id')->label('Kendaraan (opsional)')->options(fn () => static::vehicleOptions())->searchable()
                ->helperText('Boleh dikosongkan; bagian umum menetapkan kendaraan saat menyetujui.')
                ->rule(static::availabilityRule()),
            TextInput::make('driver_name')->label('Pengemudi')->maxLength(255),
        ]);
    }

    public static function workflowActions(): array
    {
        return [
            Action::make('approve')->label('Setujui')->icon(Heroicon::OutlinedCheck)->color('success')
                ->visible(fn (VehicleBooking $record) => static::canManage() && $record->state === VehicleBookingState::Requested)
                ->fillForm(fn (VehicleBooking $record) => ['vehicle_id' => $record->vehicle_id, 'driver_name' => $record->driver_name])
                ->schema([
                    Select::make('vehicle_id')->label('Kendaraan')->options(fn () => static::vehicleOptions())->required()->live()
                        ->rule(static::availabilityRule()),
                    TextInput::make('driver_name')->label('Pengemudi')
                        ->placeholder(fn (Get $get) => Vehicle::find($get('vehicle_id'))?->default_driver ?? 'Nama pengemudi'),
                ])
                ->action(fn (VehicleBooking $record, array $data) => Domain::attempt(
                    fn () => $record->approve((int) $data['vehicle_id'], $data['driver_name'] ?? null), 'Pemesanan disetujui.')),
            Action::make('reject')->label('Tolak')->icon(Heroicon::OutlinedXMark)->color('danger')
                ->visible(fn (VehicleBooking $record) => static::canManage() && $record->state === VehicleBookingState::Requested)
                ->schema([Textarea::make('note')->label('Alasan')->required()])
                ->action(fn (VehicleBooking $record, array $data) => Domain::attempt(fn () => $record->reject($data['note']), 'Pemesanan ditolak.')),
            Action::make('depart')->label('Berangkat')->icon(Heroicon::OutlinedPlay)->color('warning')
                ->visible(fn (VehicleBooking $record) => static::canOperate() && $record->state === VehicleBookingState::Approved)
                ->fillForm(fn (VehicleBooking $record) => ['odometer_start' => $record->vehicle?->odometer])
                ->schema([TextInput::make('odometer_start')->label('Odometer berangkat (km)')->numeric()->required()])
                ->action(fn (VehicleBooking $record, array $data) => Domain::attempt(fn () => $record->depart((int) $data['odometer_start']), 'Kendaraan berangkat.')),
            Action::make('return')->label('Kembali')->icon(Heroicon::OutlinedArrowUturnLeft)->color('success')
                ->visible(fn (VehicleBooking $record) => static::canOperate() && $record->state === VehicleBookingState::InUse)
                ->schema([TextInput::make('odometer_end')->label('Odometer kembali (km)')->numeric()->required()])
                ->action(fn (VehicleBooking $record, array $data) => Domain::attempt(fn () => $record->complete((int) $data['odometer_end']), 'Kendaraan sudah kembali.')),
            Action::make('cancel')->label('Batalkan')->icon(Heroicon::OutlinedNoSymbol)->color('gray')->requiresConfirmation()
                ->visible(fn (VehicleBooking $record) => static::canOperate() && in_array($record->state, [VehicleBookingState::Requested, VehicleBookingState::Approved], true))
                ->action(fn (VehicleBooking $record) => Domain::attempt(fn () => $record->cancel(), 'Pemesanan dibatalkan.')),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')->label('Waktu')->sortable()
                    ->formatStateUsing(fn (VehicleBooking $record) => $record->starts_at->translatedFormat('D, d M H:i'))
                    ->description(fn (VehicleBooking $record) => 's.d. '.$record->ends_at->translatedFormat($record->ends_at->isSameDay($record->starts_at) ? 'H:i' : 'd M H:i')),
                TextColumn::make('purpose')->label('Keperluan')->searchable(['purpose', 'destination', 'number'])->wrap()
                    ->description(fn (VehicleBooking $record) => "{$record->number} · {$record->destination}"),
                TextColumn::make('requester.name')->label('Pemohon')->placeholder('—')
                    ->description(fn (VehicleBooking $record) => $record->department?->name),
                TextColumn::make('vehicle.plate_number')->label('Kendaraan')->placeholder('Belum ditetapkan')
                    ->description(fn (VehicleBooking $record) => $record->driver_name)
                    ->url(fn (VehicleBooking $record) => $record->vehicle_id ? VehicleResource::getUrl('view', ['record' => $record->vehicle_id]) : null),
                TextColumn::make('passengers')->label('Penumpang')->suffix(' org')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distance_km')->label('Jarak')->suffix(' km')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')->label('Status')->badge()
                    ->description(fn (VehicleBooking $record) => $record->state === VehicleBookingState::Rejected ? $record->note : null),
            ])
            ->filters([
                SelectFilter::make('state')->label('Status')->options(VehicleBookingState::class)->multiple(),
                SelectFilter::make('vehicle_id')->label('Kendaraan')->relationship('vehicle', 'plate_number'),
                Filter::make('period')->label('Periode')
                    ->schema([DatePicker::make('from')->label('Dari tanggal'), DatePicker::make('until')->label('Sampai tanggal')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('starts_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('starts_at', '<=', $d))),
            ])
            ->recordActions([ActionGroup::make([...static::workflowActions(), EditAction::make(), DeleteAction::make()])->label('Proses')->button()->size('sm')->color('gray')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVehicleBookings::route('/')];
    }
}
