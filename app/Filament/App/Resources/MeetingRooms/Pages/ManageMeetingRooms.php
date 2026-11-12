<?php

namespace App\Filament\App\Resources\MeetingRooms\Pages;

use App\Filament\App\Resources\MeetingRooms\MeetingRoomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMeetingRooms extends ManageRecords
{
    protected static string $resource = MeetingRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
