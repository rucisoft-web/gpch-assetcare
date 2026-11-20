<?php

namespace App\Filament\Resources\RoomAssetTemplates\Pages;

use App\Filament\Resources\RoomAssetTemplates\RoomAssetTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoomAssetTemplates extends ListRecords
{
    protected static string $resource = RoomAssetTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Template baru')];
    }
}
