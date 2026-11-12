<?php

namespace App\Filament\App\Resources\SupplyRequests\Pages;

use App\Filament\App\Resources\SupplyRequests\SupplyRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplyRequest extends CreateRecord
{
    protected static string $resource = SupplyRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
