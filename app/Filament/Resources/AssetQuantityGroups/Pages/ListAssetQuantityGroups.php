<?php

namespace App\Filament\Resources\AssetQuantityGroups\Pages;

use App\Filament\Resources\AssetQuantityGroups\AssetQuantityGroupResource;
use Filament\Resources\Pages\ListRecords;

class ListAssetQuantityGroups extends ListRecords
{
    protected static string $resource = AssetQuantityGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('Kelompok baru')];
    }
}
