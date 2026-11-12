<?php

namespace App\Filament\App\Pages;

use App\Enums\RoomBookingState;
use App\Filament\App\Resources\RoomBookings\RoomBookingResource;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/** Jadwal mingguan seluruh ruang rapat: baris = ruang, kolom = hari. */
class JadwalRuangRapat extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Layanan Umum';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Jadwal Ruang Rapat';

    protected static ?string $title = 'Jadwal Ruang Rapat';

    protected static ?string $slug = 'jadwal-ruang-rapat';

    protected string $view = 'filament.pages.jadwal-ruang-rapat';

    #[Url]
    public ?string $minggu = null;

    protected function weekStart(): CarbonImmutable
    {
        return rescue(fn () => CarbonImmutable::parse($this->minggu), null, false)?->startOfWeek() ?? CarbonImmutable::today()->startOfWeek();
    }

    public function shiftWeek(int $weeks): void
    {
        $this->minggu = $weeks === 0 ? null : $this->weekStart()->addWeeks($weeks)->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prev')->label('Minggu lalu')->icon(Heroicon::OutlinedChevronLeft)->color('gray')->action(fn () => $this->shiftWeek(-1)),
            Action::make('today')->label('Minggu ini')->color('gray')->action(fn () => $this->shiftWeek(0)),
            Action::make('next')->label('Minggu depan')->icon(Heroicon::OutlinedChevronRight)->iconPosition('after')->color('gray')->action(fn () => $this->shiftWeek(1)),
            Action::make('book')->label('Pesan ruang')->icon(Heroicon::OutlinedPlus)
                ->visible(fn () => RoomBookingResource::canCreate())
                ->url(RoomBookingResource::getUrl('index', ['action' => 'create'])),
        ];
    }

    public function getViewData(): array
    {
        $start = $this->weekStart();
        $days = collect(range(0, 6))->map(fn (int $i) => $start->addDays($i));

        $bookings = RoomBooking::query()
            ->with('organizer')
            ->whereIn('state', [RoomBookingState::Requested->value, RoomBookingState::Approved->value])
            ->where('starts_at', '<', $start->addWeek())
            ->where('ends_at', '>', $start)
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (RoomBooking $b) => $b->meeting_room_id.'|'.$b->starts_at->toDateString());

        return [
            'days' => $days,
            'rooms' => MeetingRoom::query()->where('is_active', true)->orderBy('name')->get(),
            'bookings' => $bookings,
            'range' => $start->translatedFormat('d M').' – '.$start->addDays(6)->translatedFormat('d M Y'),
            'listUrl' => RoomBookingResource::getUrl('index'),
        ];
    }
}
