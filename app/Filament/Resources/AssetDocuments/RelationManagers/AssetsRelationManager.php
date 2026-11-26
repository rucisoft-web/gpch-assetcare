<?php

namespace App\Filament\Resources\AssetDocuments\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Aset yang ditaungi dokumen (nol, satu, atau banyak). */
class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Aset terkait';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $can = fn () => (bool) auth()->user()?->hasPermission('document.manage');

        return $table
            ->recordTitle(fn (Asset $record) => $record->asset_code.' · '.$record->name)
            ->columns([
                TextColumn::make('asset_code')->label('Kode')->fontFamily('mono')->searchable(),
                TextColumn::make('name')->label('Nama')->searchable()->wrap(),
                TextColumn::make('category.name')->label('Kategori'),
                TextColumn::make('location.full_path')->label('Lokasi')->wrap(),
            ])
            ->emptyStateHeading('Tidak ada aset tertaut')
            ->emptyStateDescription('Boleh kosong untuk dokumen tingkat hotel (mis. izin usaha).')
            ->headerActions([
                AttachAction::make()->label('Tautkan aset')->visible($can)->multiple()->preloadRecordSelect(false)
                    ->recordSelectSearchColumns(['asset_code', 'name', 'serial_number']),
            ])
            ->recordActions([
                Action::make('kartu')->label('Kartu aset')->icon('heroicon-o-cube')->url(fn (Asset $record) => AssetResource::getUrl('view', ['record' => $record])),
                DetachAction::make()->label('Lepas tautan')->visible($can),
            ]);
    }
}
