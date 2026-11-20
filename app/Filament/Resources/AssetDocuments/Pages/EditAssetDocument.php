<?php

namespace App\Filament\Resources\AssetDocuments\Pages;

use App\Filament\Resources\AssetDocuments\AssetDocumentResource;
use Filament\Resources\Pages\EditRecord;

class EditAssetDocument extends EditRecord
{
    protected static string $resource = AssetDocumentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
