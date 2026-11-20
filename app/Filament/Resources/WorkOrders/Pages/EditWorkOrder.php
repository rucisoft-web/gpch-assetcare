<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['priority']);
        $data['version'] = $this->record->version + 1;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return WorkOrderResource::getUrl('view', ['record' => $this->record]);
    }
}
