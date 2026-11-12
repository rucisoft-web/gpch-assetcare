<?php

namespace App\Filament\App\Resources\SupplyRequests\Pages;

use App\Filament\App\Resources\SupplyRequests\SupplyRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplyRequest extends ViewRecord
{
    protected static string $resource = SupplyRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [...SupplyRequestResource::workflowActions(), EditAction::make()];
    }
}
