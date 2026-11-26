<?php

namespace App\Filament\Resources\Locations\RelationManagers;

use App\Models\RoomAssetSlot;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Slot standar kamar & pemetaan aktif (slot kosong terlihat, tidak otomatis membuat aset). */
class SlotsRelationManager extends RelationManager
{
    protected static string $relationship = 'slots';

    protected static ?string $title = 'Slot perlengkapan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->isRoom();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('activeAssignment.asset', 'activeAssignment.quantityGroup', 'category'))
            ->columns([
                TextColumn::make('item_key')->label('Item key')->fontFamily('mono')->size('sm'),
                TextColumn::make('display_name')->label('Slot'),
                TextColumn::make('category.name')->label('Kategori'),
                TextColumn::make('mode')->label('Mode')->formatStateUsing(fn ($state) => $state === 'quantity' ? 'Kuantitas' : 'Per unit'),
                TextColumn::make('mapped')->label('Terpetakan ke')->state(fn ($record) => $record->activeAssignment?->asset?->label ?? $record->activeAssignment?->quantityGroup?->label)->placeholder('— kosong —'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => RoomAssetSlot::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'filled' ? 'success' : 'warning'),
            ])
            ->defaultSort('item_key');
    }
}
