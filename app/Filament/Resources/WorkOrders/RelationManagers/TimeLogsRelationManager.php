<?php

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Models\WoTimeLog;
use App\Support\Fmt;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Jam kerja teknisi (check-in/out otomatis dari aksi mulai, hold, selesai). */
class TimeLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'timeLogs';

    protected static ?string $title = 'Jam kerja';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('technician.name')->label('Teknisi'),
                TextColumn::make('started_at')->label('Mulai')->dateTime('d M H:i'),
                TextColumn::make('ended_at')->label('Selesai')->dateTime('d M H:i')->placeholder('berjalan'),
                TextColumn::make('approved_minutes')->label('Durasi disetujui')->state(fn (WoTimeLog $record) => Fmt::minutes($record->minutes())),
                TextColumn::make('note')->label('Catatan')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('koreksi')->label('Koreksi durasi')->icon('heroicon-o-pencil')
                    ->visible(fn (WoTimeLog $record) => $record->ended_at && auth()->user()->hasPermission('wo.assign'))
                    ->fillForm(fn (WoTimeLog $record) => ['approved_minutes' => $record->minutes()])
                    ->schema([TextInput::make('approved_minutes')->label('Menit disetujui')->numeric()->minValue(0)->required(), TextInput::make('note')->label('Alasan')->required()])
                    ->action(function (WoTimeLog $record, array $data) {
                        $record->auditReason = $data['note'];
                        $record->update($data);
                    }),
            ])
            ->defaultSort('started_at', 'desc');
    }
}
