<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Models\AssetLabel;
use App\Models\InstallationEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Token label (satu aktif) & riwayat cetak/pemasangan. Token utuh tidak ditampilkan. */
class LabelsRelationManager extends RelationManager
{
    protected static string $relationship = 'labels';

    protected static ?string $title = 'Label QR';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('printItems')->with('latestInstallation.actor'))
            ->columns([
                TextColumn::make('id')->label('Label #'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => AssetLabel::STATUSES[$state] ?? $state)->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('activated_at')->label('Aktif sejak')->dateTime('d M Y H:i'),
                TextColumn::make('print_items_count')->label('Dicetak'),
                TextColumn::make('install')->label('Pemasangan')->state(fn ($record) => InstallationEvent::STATUSES[$record->currentInstallationStatus()] ?? '-')
                    ->description(fn ($record) => $record->latestInstallation ? $record->latestInstallation->actor?->name.' · '.$record->latestInstallation->occurred_at->timezone(config('assetcare.display_timezone'))->format('d/m/Y H:i') : null),
                TextColumn::make('revoke_reason')->label('Alasan rotasi')->placeholder('—')->wrap(),
            ])
            ->defaultSort('id', 'desc');
    }
}
