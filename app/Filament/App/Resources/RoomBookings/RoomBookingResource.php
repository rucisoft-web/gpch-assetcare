<?php

namespace App\Filament\App\Resources\RoomBookings;

use App\Enums\RoomBookingState;
use App\Filament\App\Resources\RoomBookings\Pages\ManageRoomBookings;
use App\Filament\Concerns\OperationalPermissions;
use App\Filament\Support\Domain;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
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

class RoomBookingResource extends Resource
{
    use OperationalPermissions;

    protected static ?string $model = RoomBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Layanan Umum';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Pemesanan ruang';

    protected static ?string $pluralModelLabel = 'Pemesanan Ruang Rapat';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationBadge(): ?string
    {
        $count = static::canManage() ? RoomBooking::where('state', RoomBookingState::Requested)->count() : 0;

        return $count ?: null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Menunggu persetujuan';
    }

    public static function canEdit(Model $record): bool
    {
        return match ($record->state) {
            RoomBookingState::Requested => static::canOperate(),
            RoomBookingState::Approved => static::canManage(),
            default => false,
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Agenda rapat')->required()->maxLength(255)->columnSpanFull(),
            Select::make('meeting_room_id')->label('Ruang')->required()->live()
                ->options(fn () => MeetingRoom::query()->where('is_active', true)->orderBy('name')->get()
                    ->mapWithKeys(fn (MeetingRoom $room) => [$room->id => "{$room->name} · {$room->capacity} orang"])),
            TextInput::make('attendees')->label('Jumlah peserta')->numeric()->minValue(1)->default(5)->required()
                ->rule(fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                    $capacity = MeetingRoom::find($get('meeting_room_id'))?->capacity;

                    if ($capacity && (int) $value > $capacity) {
                        $fail("Melebihi kapasitas ruang ({$capacity} orang).");
                    }
                }),
            DateTimePicker::make('starts_at')->label('Mulai')->required()->seconds(false)->minutesStep(15)->live()
                ->default(now()->addHour()->startOfHour()),
            DateTimePicker::make('ends_at')->label('Selesai')->required()->seconds(false)->minutesStep(15)->after('starts_at')
                ->default(now()->addHours(2)->startOfHour())
                ->rule(fn (Get $get, ?RoomBooking $record) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                    $conflict = RoomBooking::conflictFor((int) $get('meeting_room_id'), Carbon::parse($get('starts_at')), Carbon::parse($value), $record?->id);

                    if ($conflict) {
                        $fail(RoomBooking::conflictMessage($conflict));
                    }
                }),
            Select::make('department_id')->label('Departemen')->options(fn () => Domain::departmentOptions())->searchable(),
            Select::make('organizer_id')->label('Penyelenggara')->options(fn () => static::memberOptions())->searchable()->default(fn () => auth()->id()),
            Textarea::make('needs')->label('Kebutuhan')->placeholder('Konsumsi 20 pax, proyektor, sound system')->columnSpanFull(),
        ]);
    }

    /** Persetujuan, penolakan, dan pembatalan. */
    public static function workflowActions(): array
    {
        return [
            Action::make('approve')->label('Setujui')->icon(Heroicon::OutlinedCheck)->color('success')
                ->visible(fn (RoomBooking $record) => static::canManage() && $record->state === RoomBookingState::Requested)
                ->action(fn (RoomBooking $record) => Domain::attempt(fn () => $record->approve(), 'Pemesanan disetujui.')),
            Action::make('reject')->label('Tolak')->icon(Heroicon::OutlinedXMark)->color('danger')
                ->visible(fn (RoomBooking $record) => static::canManage() && $record->state === RoomBookingState::Requested)
                ->schema([Textarea::make('note')->label('Alasan')->required()])
                ->action(fn (RoomBooking $record, array $data) => Domain::attempt(fn () => $record->reject($data['note']), 'Pemesanan ditolak.')),
            Action::make('cancel')->label('Batalkan')->icon(Heroicon::OutlinedNoSymbol)->color('gray')->requiresConfirmation()
                ->visible(fn (RoomBooking $record) => static::canOperate() && in_array($record->state, [RoomBookingState::Requested, RoomBookingState::Approved], true))
                ->action(fn (RoomBooking $record) => Domain::attempt(fn () => $record->cancel(), 'Pemesanan dibatalkan.')),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')->label('Waktu')->sortable()
                    ->formatStateUsing(fn (RoomBooking $record) => $record->starts_at->translatedFormat('D, d M Y'))
                    ->description(fn (RoomBooking $record) => $record->starts_at->format('H:i').'–'.$record->ends_at->format('H:i')),
                TextColumn::make('title')->label('Agenda')->searchable(['title', 'number'])->wrap()
                    ->description(fn (RoomBooking $record) => $record->number),
                TextColumn::make('room.name')->label('Ruang'),
                TextColumn::make('organizer.name')->label('Penyelenggara')->placeholder('—')
                    ->description(fn (RoomBooking $record) => $record->department?->name),
                TextColumn::make('attendees')->label('Peserta')->suffix(' org'),
                TextColumn::make('needs')->label('Kebutuhan')->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')->label('Status')->badge()
                    ->description(fn (RoomBooking $record) => $record->state === RoomBookingState::Rejected ? $record->note : null),
            ])
            ->filters([
                SelectFilter::make('state')->label('Status')->options(RoomBookingState::class)->multiple(),
                SelectFilter::make('meeting_room_id')->label('Ruang')->relationship('room', 'name'),
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
        return ['index' => ManageRoomBookings::route('/')];
    }
}
