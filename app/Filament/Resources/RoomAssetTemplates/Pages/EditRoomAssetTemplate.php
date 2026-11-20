<?php

namespace App\Filament\Resources\RoomAssetTemplates\Pages;

use App\Filament\Resources\RoomAssetTemplates\RoomAssetTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditRoomAssetTemplate extends EditRecord
{
    protected static string $resource = RoomAssetTemplateResource::class;

    public function getTitle(): string
    {
        return $this->record->code.' v'.$this->record->version.' ('.\App\Models\RoomAssetTemplate::STATUSES[$this->record->status].')';
    }

    protected function getHeaderActions(): array
    {
        return array_values(array_filter(RoomAssetTemplateResource::recordActions(), fn ($a) => $a->getName() !== 'edit'));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['prepared_by'] = auth()->id();

        return $data;
    }
}
