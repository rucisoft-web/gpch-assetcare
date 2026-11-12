<?php

namespace App\Filament\App\Resources\LegalDocuments\Pages;

use App\Filament\App\Resources\LegalDocuments\LegalDocumentResource;
use App\Models\LegalDocument;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageLegalDocuments extends ManageRecords
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getTabs(): array
    {
        return [
            'tindakan' => Tab::make('Perlu tindakan')->icon(Heroicon::OutlinedBellAlert)
                ->modifyQueryUsing(fn (Builder $query) => $query->needsAction())
                ->badge(fn () => LegalDocument::query()->needsAction()->count() ?: null)->badgeColor('warning'),
            'semua' => Tab::make('Semua dokumen'),
        ];
    }
}
