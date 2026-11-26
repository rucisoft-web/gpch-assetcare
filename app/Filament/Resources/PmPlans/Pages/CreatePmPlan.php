<?php

namespace App\Filament\Resources\PmPlans\Pages;

use App\Filament\Resources\PmPlans\PmPlanResource;
use App\Services\PlannedMaintenanceService;
use Filament\Resources\Pages\CreateRecord;

class CreatePmPlan extends CreateRecord
{
    protected static string $resource = PmPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['created_by' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        app(PlannedMaintenanceService::class)->syncSchedules($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return PmPlanResource::getUrl('edit', ['record' => $this->record]);
    }
}
