<?php

namespace App\Filament\RelationManagers;

use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Riwayat transisi status (aktor, waktu, alasan) — hanya baca. */
class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusEvents';

    protected static ?string $title = 'Riwayat status';

    public function table(Table $table): Table
    {
        $labels = WorkOrder::STATUSES + ServiceRequest::STATUSES;
        $actions = WorkOrderService::ACTION_LABELS + [
            'create' => 'Dibuat', 'acknowledge' => 'Diakui', 'triage' => 'Triase', 'link' => 'Ditautkan ke WO', 'reject' => 'Ditolak',
            'mark_duplicate' => 'Ditandai duplikat', 'sync_wo' => 'Mengikuti WO', 'finalize_cost' => 'Biaya final', 'room_block' => 'Usulan blokir kamar',
        ];

        return $table
            ->columns([
                TextColumn::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('action')->label('Aksi')->formatStateUsing(fn ($state) => $actions[$state] ?? $state),
                TextColumn::make('from_status')->label('Dari')->formatStateUsing(fn ($state) => $labels[$state] ?? $state)->placeholder('—'),
                TextColumn::make('to_status')->label('Menjadi')->badge()->color('gray')->formatStateUsing(fn ($state) => $labels[$state] ?? $state),
                TextColumn::make('reason')->label('Alasan / catatan')->wrap()->placeholder('—'),
                TextColumn::make('actor.name')->label('Oleh')->placeholder('sistem'),
            ])
            ->defaultSort('occurred_at', 'desc');
    }
}
