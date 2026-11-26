<?php

namespace App\Filament\Resources\AssetQuantityGroups\Pages;

use App\Filament\Resources\AssetQuantityGroups\AssetQuantityGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetQuantityGroup extends CreateRecord
{
    protected static string $resource = AssetQuantityGroupResource::class;

    protected function afterCreate(): void
    {
        \App\Models\AssetLabel::issueFor(null, $this->record);
    }
}
