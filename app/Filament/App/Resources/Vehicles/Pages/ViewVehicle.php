<?php

namespace App\Filament\App\Resources\Vehicles\Pages;

use App\Filament\App\Resources\Vehicles\VehicleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
