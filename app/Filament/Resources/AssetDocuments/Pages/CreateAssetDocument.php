<?php

namespace App\Filament\Resources\AssetDocuments\Pages;

use App\Filament\Resources\AssetDocuments\AssetDocumentResource;
use App\Services\DocumentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAssetDocument extends CreateRecord
{
    protected static string $resource = AssetDocumentResource::class;

    protected function afterFill(): void
    {
        if ($asset = request()->integer('asset')) {
            $this->data['asset_ids'] = [$asset];
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(DocumentService::class)->create(self::withFiles($data), auth()->user());
    }

    /** Ubah isian unggahan menjadi peta path => nama asli untuk DocumentService. */
    public static function withFiles(array $data): array
    {
        $paths = array_values(array_filter((array) ($data['files'] ?? [])));
        $names = (array) ($data['files_names'] ?? []);
        $data['files'] = collect($paths)->mapWithKeys(fn ($p) => [$p => $names[$p] ?? basename($p)])->all();
        unset($data['files_names']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
