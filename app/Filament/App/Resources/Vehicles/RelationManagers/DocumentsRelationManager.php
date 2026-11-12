<?php

namespace App\Filament\App\Resources\Vehicles\RelationManagers;

use App\Filament\App\Resources\LegalDocuments\LegalDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumen';

    public function isReadOnly(): bool
    {
        return auth()->user()?->roleIn($this->getOwnerRecord()->company_id)?->canOperate() !== true;
    }

    public function form(Schema $schema): Schema
    {
        return LegalDocumentResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return LegalDocumentResource::table($table)->headerActions([CreateAction::make()]);
    }
}
