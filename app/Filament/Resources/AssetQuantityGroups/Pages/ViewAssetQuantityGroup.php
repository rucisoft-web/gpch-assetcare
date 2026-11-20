<?php

namespace App\Filament\Resources\AssetQuantityGroups\Pages;

use App\Filament\Resources\AssetQuantityGroups\AssetQuantityGroupResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAssetQuantityGroup extends ViewRecord
{
    protected static string $resource = AssetQuantityGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [AssetQuantityGroupResource::countAction()->after(fn () => $this->redirect(static::getUrl(['record' => $this->record])))];
    }
}
