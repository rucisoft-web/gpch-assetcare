<?php

namespace App\Filament\Resources\GenerationBatches;

use App\Filament\Concerns\HasPermissions;
use App\Filament\Support\F;
use App\Models\GenerationBatch;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Riwayat batch generate aset (idempotency key, template & versi, hasil per kamar). */
class GenerationBatchResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = GenerationBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengendalian Aset';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Batch generate';

    protected static ?string $pluralModelLabel = 'Riwayat generate';

    protected static ?string $navigationLabel = 'Riwayat generate';

    protected static ?string $viewPermission = 'generation.run';

    protected static ?string $editPermission = null;

    public const COLORS = ['previewed' => 'gray', 'committing' => 'info', 'completed' => 'success', 'partial' => 'warning', 'failed' => 'danger', 'cancelled' => 'gray'];

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => GenerationBatch::STATUSES[$state] ?? $state)->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextEntry::make('template.code')->label('Template')->formatStateUsing(fn ($state, $record) => $state.' v'.$record->template_version),
                TextEntry::make('room_ids')->label('Kamar')->state(fn ($record) => count($record->room_ids ?? [])),
                TextEntry::make('created_assets')->label('Kandidat dibuat'),
                TextEntry::make('matched_assets')->label('Aset lama dicocokkan'),
                TextEntry::make('creator.name')->label('Operator'),
                TextEntry::make('committed_at')->label('Diproses')->dateTime('d M Y H:i')->placeholder('—'),
                TextEntry::make('idempotency_key')->label('Kunci idempotensi')->copyable()->fontFamily('mono')->size('xs'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Nomor')->weight('bold')->searchable(),
                F::statusColumn('status', GenerationBatch::STATUSES, self::COLORS),
                TextColumn::make('template.code')->label('Template')->formatStateUsing(fn ($state, $record) => $state.' v'.$record->template_version),
                TextColumn::make('rooms')->label('Kamar')->state(fn ($record) => count($record->room_ids ?? [])),
                TextColumn::make('created_assets')->label('Dibuat'),
                TextColumn::make('matched_assets')->label('Dicocokkan'),
                TextColumn::make('creator.name')->label('Operator'),
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ResultsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGenerationBatches::route('/'),
            'view' => Pages\ViewGenerationBatch::route('/{record}'),
        ];
    }
}
