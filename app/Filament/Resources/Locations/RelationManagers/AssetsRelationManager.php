<?php

namespace App\Filament\Resources\Locations\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Aset di lokasi ini';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset_code')->label('Kode')->fontFamily('mono')->searchable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('category.name')->label('Kategori'),
                TextColumn::make('lifecycle_status')->label('Siklus')->badge()->formatStateUsing(fn ($state) => Asset::LIFECYCLE[$state] ?? $state),
                TextColumn::make('verification_status')->label('Verifikasi')->badge()->formatStateUsing(fn ($state) => Asset::VERIFICATION[$state] ?? $state)
                    ->color(fn ($state) => $state === 'verified' ? 'success' : 'warning'),
            ])
            ->recordActions([Action::make('buka')->label('Kartu aset')->url(fn ($record) => AssetResource::getUrl('view', ['record' => $record]))]);
    }
}
