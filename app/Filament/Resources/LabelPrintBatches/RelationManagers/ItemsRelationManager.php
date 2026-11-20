<?php

namespace App\Filament\Resources\LabelPrintBatches\RelationManagers;

use App\Models\InstallationEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Isi batch';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sequence')->label('#'),
                TextColumn::make('sheet_no')->label('Lembar')->formatStateUsing(fn ($state, $record) => $state.' / sel '.$record->cell_no),
                TextColumn::make('code_snapshot')->label('Kode')->weight('bold')->searchable(),
                TextColumn::make('name_snapshot')->label('Nama')->wrap()->searchable(),
                TextColumn::make('location_snapshot')->label('Lokasi pada label'),
                TextColumn::make('label.status')->label('Label')->badge()->formatStateUsing(fn ($state) => $state === 'active' ? 'Aktif' : 'Tidak berlaku')->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('install')->label('Pemasangan')->state(fn ($record) => InstallationEvent::STATUSES[$record->label?->currentInstallationStatus()] ?? '—'),
            ])
            ->defaultSort('sequence');
    }
}
