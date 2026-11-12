<?php

namespace App\Filament\App\Resources\SupplyRequests\Pages;

use App\Filament\App\Resources\SupplyRequests\SupplyRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditSupplyRequest extends EditRecord
{
    protected static string $resource = SupplyRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
