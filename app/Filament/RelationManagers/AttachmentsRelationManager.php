<?php

namespace App\Filament\RelationManagers;

use App\Filament\Support\F;
use App\Models\Attachment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/** Dokumen & foto privat (aset, laporan, work order). Unduhan selalu melalui otorisasi. */
class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Foto & dokumen';

    public const KINDS = [
        'photo' => 'Foto', 'before' => 'Foto sebelum', 'after' => 'Foto sesudah', 'evidence' => 'Bukti lain',
        'nameplate' => 'Foto nameplate', 'manual' => 'Manual', 'warranty' => 'Garansi', 'certificate' => 'Sertifikat', 'invoice' => 'Invoice / nota',
    ];

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->label('Jenis')->options(self::KINDS)->required()->native(false)->default('photo'),
            F::upload('path', 'Berkas (JPG/PNG/PDF, maks 8 MB)', 'attachments/'.now()->format('Y/m'))->required()->storeFileNamesIn('original_name'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->label('Jenis')->formatStateUsing(fn ($state) => self::KINDS[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('original_name')->label('Nama berkas')->placeholder('—')->wrap(),
                TextColumn::make('uploader.name')->label('Pengunggah'),
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
            ])
            ->emptyStateHeading('Belum ada lampiran')
            ->headerActions([
                CreateAction::make()->label('Unggah')->icon('heroicon-o-camera')
                    ->visible(fn () => (bool) auth()->user()?->hasAnyPermission(['asset.edit', 'asset.verify', 'wo.execute', 'wo.assign', 'request.create']))
                    ->mutateDataUsing(function (array $data) {
                        $disk = Storage::disk('local');

                        return $data + ['disk' => 'local', 'uploaded_by' => auth()->id(), 'mime' => $disk->mimeType($data['path']), 'size' => $disk->size($data['path']), 'checksum' => hash('sha256', $disk->get($data['path']))];
                    }),
            ])
            ->recordActions([
                Action::make('unduh')->label('Buka')->icon('heroicon-o-arrow-down-tray')->url(fn (Attachment $record) => route('attachments.show', $record))->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
