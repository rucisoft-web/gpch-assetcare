<?php

namespace App\Filament\Resources\AssetTransfers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Aset';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('asset.asset_code')->label('Kode')->weight('bold'),
            TextColumn::make('asset.name')->label('Nama'),
            TextColumn::make('fromLocation.full_path')->label('Lokasi asal')->wrap(),
            TextColumn::make('condition_out')->label('Kondisi kirim')->placeholder('—'),
            TextColumn::make('condition_in')->label('Kondisi terima')->placeholder('—'),
            TextColumn::make('asset.installation_status')->label('Label')->formatStateUsing(fn ($state) => \App\Models\InstallationEvent::STATUSES[$state] ?? $state),
        ]);
    }
}
