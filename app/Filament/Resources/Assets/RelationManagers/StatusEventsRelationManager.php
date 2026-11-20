<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Models\Asset;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Riwayat status & lokasi (tidak dapat diubah). */
class StatusEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusEvents';

    protected static ?string $title = 'Riwayat';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('dimension')->label('Dimensi')->badge()->color('gray')->formatStateUsing(fn ($state) => Asset::DIMENSIONS[$state] ?? $state),
                TextColumn::make('old_label')->label('Dari')->placeholder('—')->wrap(),
                TextColumn::make('new_label')->label('Menjadi')->placeholder('—')->wrap(),
                TextColumn::make('reason')->label('Alasan')->wrap()->placeholder('—'),
                TextColumn::make('actor.name')->label('Oleh')->placeholder('sistem'),
            ])
            ->filters([SelectFilter::make('dimension')->label('Dimensi')->options(Asset::DIMENSIONS)])
            ->defaultSort('occurred_at', 'desc');
    }
}
