<?php

namespace App\Filament\Resources\AssetDocuments\RelationManagers;

use App\Models\Attachment;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Berkas digital dokumen: semua versi tersimpan, berikut hash SHA-256 untuk deteksi perubahan. */
class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Berkas digital';

    public function table(Table $table): Table
    {
        $latest = $this->getOwnerRecord()->files()->max('file_version');

        return $table
            ->columns([
                TextColumn::make('file_version')->label('Versi')->formatStateUsing(fn ($state) => 'v'.$state.($state == $latest ? ' (terbaru)' : ''))->badge()
                    ->color(fn ($state) => $state == $latest ? 'success' : 'gray'),
                TextColumn::make('original_name')->label('Nama berkas')->wrap(),
                TextColumn::make('note')->label('Keterangan')->placeholder('—')->wrap(),
                TextColumn::make('size')->label('Ukuran')->formatStateUsing(fn ($state) => $state ? number_format($state / 1024, 0, ',', '.').' KB' : '—'),
                TextColumn::make('checksum')->label('SHA-256')->fontFamily('mono')->limit(16)->tooltip(fn ($state) => $state)->copyable(),
                TextColumn::make('uploader.name')->label('Pengunggah'),
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
            ])
            ->emptyStateHeading('Belum ada berkas digital')
            ->emptyStateDescription('Kartu dokumen tetap sah tanpa pindaian. Gunakan "Unggah berkas / versi baru" di atas.')
            ->recordActions([
                Action::make('buka')->label('Buka')->icon('heroicon-o-arrow-down-tray')->url(fn (Attachment $record) => route('attachments.show', $record))->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
