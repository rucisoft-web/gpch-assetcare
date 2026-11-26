<?php

namespace App\Filament\Resources\ApprovalLimits\Pages;

use App\Filament\Resources\ApprovalLimits\ApprovalLimitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageApprovalLimits extends ManageRecords
{
    protected static string $resource = ApprovalLimitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
