<?php

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Support\F;
use App\Models\ServiceRequest;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Laporan yang ditangani work order ini (pelapor menerima pembaruan). */
class RequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'requests';

    protected static ?string $title = 'Laporan terkait';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Nomor')->weight('bold'),
                F::statusColumn('status', ServiceRequest::STATUSES, ServiceRequest::STATUS_COLORS),
                TextColumn::make('symptom')->label('Gejala')->wrap()->limit(80),
                TextColumn::make('reporter.name')->label('Pelapor'),
                TextColumn::make('received_at')->label('Diterima')->dateTime('d M H:i'),
            ])
            ->recordActions([Action::make('buka')->label('Buka')->url(fn ($record) => ServiceRequestResource::getUrl('view', ['record' => $record]))]);
    }
}
