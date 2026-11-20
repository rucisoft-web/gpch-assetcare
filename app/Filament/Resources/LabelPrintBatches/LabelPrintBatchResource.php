<?php

namespace App\Filament\Resources\LabelPrintBatches;

use App\Filament\Concerns\HasPermissions;
use App\Filament\Support\F;
use App\Models\LabelPrintBatch;
use App\Services\LabelService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Riwayat batch cetak label (versi template, media, operator, alasan cetak ulang). */
class LabelPrintBatchResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = LabelPrintBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengendalian Aset';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Batch cetak label';

    protected static ?string $pluralModelLabel = 'Riwayat cetak label';

    protected static ?string $navigationLabel = 'Riwayat cetak label';

    protected static ?string $viewPermission = 'label.print';

    protected static ?string $editPermission = null;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => LabelPrintBatch::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['ready' => 'warning', 'downloaded' => 'info', 'printed' => 'success'][$state] ?? 'gray'),
                TextEntry::make('media_profile')->label('Media')->formatStateUsing(fn ($state) => config('assetcare.label_profiles.'.$state.'.name', $state)),
                TextEntry::make('label_count')->label('Label')->formatStateUsing(fn ($state, $record) => $state.' label · '.$record->sheet_count.' lembar · mulai sel '.$record->start_cell),
                TextEntry::make('template_version')->label('Versi template'),
                TextEntry::make('purpose')->label('Tujuan')->formatStateUsing(fn ($state) => $state === 'reprint' ? 'Cetak ulang' : 'Cetak baru'),
                TextEntry::make('reprint_reason')->label('Alasan cetak ulang')->placeholder('—'),
                TextEntry::make('operator.name')->label('Operator'),
                TextEntry::make('generated_at')->label('Dibuat')->dateTime('d M Y H:i'),
                TextEntry::make('downloaded_at')->label('Diunduh')->dateTime('d M Y H:i')->placeholder('belum'),
                TextEntry::make('printed_confirmed_at')->label('Cetak dikonfirmasi')->dateTime('d M Y H:i')->placeholder('belum')
                    ->formatStateUsing(fn ($state, $record) => \App\Support\Fmt::dt($state).($record->printConfirmer ? ' · '.$record->printConfirmer->name : '')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Nomor')->weight('bold')->searchable(),
                F::statusColumn('status', LabelPrintBatch::STATUSES, ['ready' => 'warning', 'downloaded' => 'info', 'printed' => 'success', 'cancelled' => 'gray']),
                TextColumn::make('purpose')->label('Tujuan')->formatStateUsing(fn ($state) => $state === 'reprint' ? 'Cetak ulang' : 'Baru')->badge()->color(fn ($state) => $state === 'reprint' ? 'warning' : 'gray'),
                TextColumn::make('label_count')->label('Label'),
                TextColumn::make('sheet_count')->label('Lembar'),
                TextColumn::make('media_profile')->label('Media'),
                TextColumn::make('operator.name')->label('Operator'),
                TextColumn::make('generated_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->recordActions([ViewAction::make(), static::downloadAction()])
            ->defaultSort('generated_at', 'desc');
    }

    public static function downloadAction(bool $guides = false): Action
    {
        return Action::make($guides ? 'uji' : 'unduh')->label($guides ? 'PDF uji (garis bantu)' : 'Unduh PDF')
            ->icon('heroicon-o-arrow-down-tray')->color($guides ? 'gray' : 'primary')
            ->url(fn (LabelPrintBatch $record) => route('labels.pdf', ['batch' => $record, 'guides' => $guides ? 1 : null]))->openUrlInNewTab();
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLabelPrintBatches::route('/'),
            'view' => Pages\ViewLabelPrintBatch::route('/{record}'),
        ];
    }
}
