<?php

namespace App\Filament\App\Resources\Vehicles\RelationManagers;

use App\Filament\App\Resources\VehicleBookings\VehicleBookingResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Pemakaian';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return VehicleBookingResource::table($table)->defaultSort('starts_at', 'desc')->recordActions([]);
    }
}
