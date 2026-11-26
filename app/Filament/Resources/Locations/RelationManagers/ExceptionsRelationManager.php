<?php

namespace App\Filament\Resources\Locations\RelationManagers;

use App\Models\AssetCategory;
use App\Models\RoomException;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Pengecualian kamar: jumlah berbeda, tambahan, tidak tersedia, substitusi — alasan & pemeriksa tercatat. */
class ExceptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'exceptions';

    protected static ?string $title = 'Pengecualian kamar';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->isRoom();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('item_key')->label('Item key')->required()->maxLength(40)->helperText('Sama dengan item_key template, mis. bed, minibar; atau kunci baru untuk tambahan'),
            Select::make('type')->label('Jenis')->options(RoomException::TYPES)->required()->native(false)->live(),
            TextInput::make('quantity')->label('Jumlah aktual / tambahan')->numeric()->minValue(0)
                ->visible(fn (Get $get) => in_array($get('type'), ['quantity', 'added'], true)),
            Select::make('substitute_category_id')->label('Kategori pengganti')->options(fn () => AssetCategory::orderBy('name')->pluck('name', 'id'))->native(false)
                ->visible(fn (Get $get) => in_array($get('type'), ['substitute', 'added'], true)),
            TextInput::make('substitute_name')->label('Nama standar pengganti')->visible(fn (Get $get) => in_array($get('type'), ['substitute', 'added'], true)),
            TextInput::make('reason')->label('Alasan')->required()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item_key')->label('Item key')->fontFamily('mono'),
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn ($state) => RoomException::TYPES[$state] ?? $state),
                TextColumn::make('quantity')->label('Jumlah')->placeholder('—'),
                TextColumn::make('reason')->label('Alasan')->wrap(),
                TextColumn::make('recorder.name')->label('Dicatat'),
                TextColumn::make('reviewer.name')->label('Diperiksa')->placeholder('belum'),
            ])
            ->headerActions([
                CreateAction::make()->visible(fn () => auth()->user()?->hasAnyPermission(['template.manage', 'generation.run']))
                    ->mutateDataUsing(fn (array $data) => $data + ['recorded_by' => auth()->id()]),
            ])
            ->recordActions([
                Action::make('periksa')->label('Tandai diperiksa')->icon('heroicon-o-check')
                    ->visible(fn ($record) => ! $record->reviewed_by && auth()->user()?->hasPermission('template.publish') && (int) $record->recorded_by !== (int) auth()->id())
                    ->action(fn ($record) => $record->update(['reviewed_by' => auth()->id(), 'reviewed_at' => now()])),
                EditAction::make()->visible(fn () => auth()->user()?->hasAnyPermission(['template.manage', 'generation.run'])),
            ]);
    }
}
