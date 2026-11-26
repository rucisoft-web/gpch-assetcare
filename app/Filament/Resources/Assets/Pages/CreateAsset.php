<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\AssetLabel;
use Filament\Resources\Pages\CreateRecord;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['lifecycle_status' => 'draft', 'verification_status' => 'unverified'];
    }

    protected function afterCreate(): void
    {
        AssetLabel::issueFor($this->record); // token QR aktif (belum dicetak)
    }

    protected function getRedirectUrl(): string
    {
        return AssetResource::getUrl('view', ['record' => $this->record]);
    }
}
