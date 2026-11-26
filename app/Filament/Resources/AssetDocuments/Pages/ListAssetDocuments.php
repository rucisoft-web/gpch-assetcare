<?php

namespace App\Filament\Resources\AssetDocuments\Pages;

use App\Filament\Resources\AssetDocuments\AssetDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetDocuments extends ListRecords
{
    protected static string $resource = AssetDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('unggahCepat')->label('Unggah cepat (banyak berkas)')->icon('heroicon-o-arrow-up-tray')->color('success')
                ->visible(fn () => (bool) auth()->user()?->hasPermission('document.manage'))
                ->modalDescription('Unggah satu atau beberapa berkas legalitas sekaligus dan tautkan ke aset. Detail kartu dokumen dapat dilengkapi kemudian.')
                ->schema(fn (\Filament\Schemas\Schema $schema) => $schema->components(\App\Filament\Resources\Assets\RelationManagers\DocumentsRelationManager::quickUploadFields(withAssets: true)))
                ->fillForm(['per_file' => true, 'no_expiry' => false])
                ->action(fn (array $data) => \App\Filament\Support\F::attempt(fn () => \App\Filament\Resources\Assets\RelationManagers\DocumentsRelationManager::runQuickUpload($data, (array) ($data['asset_ids'] ?? [])), 'Dokumen legalitas tersimpan')),
            CreateAction::make()->label('Daftarkan dokumen'),
        ];
    }
}
