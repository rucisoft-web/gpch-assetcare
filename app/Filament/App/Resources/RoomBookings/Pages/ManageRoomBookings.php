<?php

namespace App\Filament\App\Resources\RoomBookings\Pages;

use App\Enums\RoomBookingState;
use App\Filament\App\Pages\JadwalRuangRapat;
use App\Filament\App\Resources\RoomBookings\RoomBookingResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageRoomBookings extends ManageRecords
{
    protected static string $resource = RoomBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedule')->label('Lihat jadwal mingguan')->icon(Heroicon::OutlinedCalendar)->color('gray')
                ->url(JadwalRuangRapat::getUrl()),
            CreateAction::make()->label('Pesan ruang'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'mendatang' => Tab::make('Mendatang')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('ends_at', '>=', now())->whereIn('state', ['requested', 'approved'])),
            'hari_ini' => Tab::make('Hari ini')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('starts_at', today())),
            'menunggu' => Tab::make('Menunggu persetujuan')->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', RoomBookingState::Requested->value)),
            'semua' => Tab::make('Semua')->modifyQueryUsing(fn (Builder $query) => $query->reorder('starts_at', 'desc')),
        ];
    }
}
