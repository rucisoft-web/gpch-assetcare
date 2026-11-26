<?php

namespace App\Filament\Resources\RoomBlocks;

use App\Filament\Concerns\HasPermissions;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\F;
use App\Models\RoomBlock;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Usulan blokir kamar dari Engineering; keputusan & status PMS tetap di Front Office. */
class RoomBlockResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = RoomBlock::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string|\UnitEnum|null $navigationGroup = 'Pemeliharaan';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Blokir kamar';

    protected static ?string $pluralModelLabel = 'Blokir kamar';

    protected static ?string $navigationLabel = 'Blokir kamar';

    protected static ?string $viewPermission = 'wo.view';

    protected static ?string $editPermission = null;

    public const COLORS = ['proposed' => 'warning', 'confirmed' => 'danger', 'rejected' => 'gray', 'released' => 'success'];

    public static function canViewAny(): bool
    {
        return (bool) static::user()?->hasAnyPermission(['wo.view', 'room.block_confirm']);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = RoomBlock::where('status', 'proposed')->count();

        return static::user()?->hasPermission('room.block_confirm') && $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        $fo = fn () => auth()->user()->hasPermission('room.block_confirm');

        return $table
            ->columns([
                TextColumn::make('room.full_path')->label('Kamar')->weight('bold')->wrap(),
                F::statusColumn('status', RoomBlock::STATUSES, self::COLORS),
                TextColumn::make('reason')->label('Alasan')->wrap(),
                TextColumn::make('workOrder.number')->label('WO')->url(fn ($record) => $record->workOrder ? WorkOrderResource::getUrl('view', ['record' => $record->workOrder]) : null),
                TextColumn::make('proposer.name')->label('Diusulkan'),
                TextColumn::make('started_at')->label('Mulai')->dateTime('d M H:i')->placeholder('—'),
                TextColumn::make('ended_at')->label('Selesai')->dateTime('d M H:i')->placeholder('—'),
                TextColumn::make('confirmer.name')->label('FO')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('konfirmasi')->label('Konfirmasi blokir')->color('danger')->visible(fn (RoomBlock $record) => $record->status === 'proposed' && $fo())
                    ->requiresConfirmation()->modalDescription('Front Office memblokir kamar di PMS secara manual; aplikasi hanya mencatat.')
                    ->action(fn (RoomBlock $record) => F::attempt(fn () => $record->update(['status' => 'confirmed', 'started_at' => now(), 'fo_confirmed_by' => auth()->id(), 'fo_confirmed_at' => now()]), 'Blokir dikonfirmasi')),
                Action::make('tolak')->label('Tolak')->visible(fn (RoomBlock $record) => $record->status === 'proposed' && $fo())
                    ->schema([Textarea::make('note')->label('Alasan')->required()])
                    ->action(function (RoomBlock $record, array $data) {
                        $record->auditReason = $data['note'];
                        F::attempt(fn () => $record->update(['status' => 'rejected', 'fo_confirmed_by' => auth()->id(), 'fo_confirmed_at' => now()]), 'Usulan ditolak');
                    }),
                Action::make('lepas')->label('Lepas blokir')->color('success')->visible(fn (RoomBlock $record) => $record->status === 'confirmed' && $fo())
                    ->requiresConfirmation()->action(fn (RoomBlock $record) => F::attempt(fn () => $record->update(['status' => 'released', 'ended_at' => now()]), 'Kamar dapat dijual kembali')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRoomBlocks::route('/')];
    }
}
