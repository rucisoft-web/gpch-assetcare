<?php

namespace App\Filament\App\Resources\VehicleBookings\Pages;

use App\Enums\VehicleBookingState;
use App\Filament\App\Resources\VehicleBookings\VehicleBookingResource;
use App\Models\VehicleBooking;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageVehicleBookings extends ManageRecords
{
    protected static string $resource = VehicleBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Pesan kendaraan')];
    }

    public function getTabs(): array
    {
        return [
            'aktif' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('state', VehicleBooking::OPEN_STATES)),
            'menunggu' => Tab::make('Menunggu persetujuan')->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', VehicleBookingState::Requested->value)),
            'dipakai' => Tab::make('Sedang dipakai')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', VehicleBookingState::InUse->value)),
            'semua' => Tab::make('Semua')->modifyQueryUsing(fn (Builder $query) => $query->reorder('starts_at', 'desc')),
        ];
    }
}
