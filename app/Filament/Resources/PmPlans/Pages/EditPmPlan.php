<?php

namespace App\Filament\Resources\PmPlans\Pages;

use App\Filament\Resources\PmPlans\PmPlanResource;
use App\Services\PlannedMaintenanceService;
use Filament\Resources\Pages\EditRecord;

class EditPmPlan extends EditRecord
{
    protected static string $resource = PmPlanResource::class;

    protected function afterSave(): void
    {
        app(PlannedMaintenanceService::class)->syncSchedules($this->record);
    }
}
