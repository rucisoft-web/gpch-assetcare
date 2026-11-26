<?php

namespace App\Filament\Resources\RoomAssetTemplates\Pages;

use App\Filament\Resources\RoomAssetTemplates\RoomAssetTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoomAssetTemplate extends CreateRecord
{
    protected static string $resource = RoomAssetTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['version' => 1, 'status' => 'draft', 'prepared_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return RoomAssetTemplateResource::getUrl('index');
    }
}
