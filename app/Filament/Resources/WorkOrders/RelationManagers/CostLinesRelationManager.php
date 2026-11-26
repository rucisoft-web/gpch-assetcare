<?php

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Filament\Support\F;
use App\Models\WoCostLine;
use App\Services\WorkOrderService;
use App\Support\Fmt;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Biaya WO: material, jasa vendor, tenaga internal (alokasi, bukan kas keluar), biaya lain. */
class CostLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'costLines';

    protected static ?string $title = 'Biaya';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $u = auth()->user();

        return (bool) $u?->hasAnyPermission(['wo.execute', 'wo.assign', 'wo.cost_finalize', 'wo.approve']);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('type')->label('Jenis')->options(WoCostLine::TYPES)->required()->native(false),
            TextInput::make('description')->label('Uraian')->required()->maxLength(200),
            TextInput::make('quantity')->label('Jumlah')->numeric()->default(1)->required(),
            TextInput::make('unit_cost')->label('Harga satuan')->numeric()->prefix('Rp')->minValue(0)->required(),
            TextInput::make('invoice_ref')->label('Ref. nota / invoice'),
        ]);
    }

    public function table(Table $table): Table
    {
        $fin = (bool) auth()->user()?->canSeeFinancials();

        return $table
            ->columns([
                TextColumn::make('type')->label('Jenis')->formatStateUsing(fn ($state) => WoCostLine::TYPES[$state] ?? $state)->badge()->color(fn ($state) => $state === 'labor' ? 'gray' : 'info'),
                TextColumn::make('description')->label('Uraian')->wrap(),
                TextColumn::make('quantity')->label('Jml')->formatStateUsing(fn ($state) => Fmt::num($state, 2)),
                TextColumn::make('unit_cost')->label('Harga')->formatStateUsing(fn ($state) => Fmt::rp($state))->visible($fin),
                TextColumn::make('amount')->label('Jumlah')->formatStateUsing(fn ($state) => Fmt::rp($state))->visible($fin)
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Fmt::rp($state))),
                TextColumn::make('cost_status')->label('Status')->badge()->formatStateUsing(fn ($state) => $state === 'final' ? 'Final' : 'Sementara')
                    ->color(fn ($state) => $state === 'final' ? 'success' : 'warning'),
                TextColumn::make('invoice_ref')->label('Ref.')->placeholder('—')->toggleable(),
                TextColumn::make('creator.name')->label('Oleh')->toggleable(),
            ])
            ->emptyStateHeading('Belum ada biaya')
            ->emptyStateDescription('Bila memang tanpa biaya, isi alasannya saat menyelesaikan pekerjaan.')
            ->headerActions([
                CreateAction::make()->label('Tambah biaya')
                    ->visible(fn () => auth()->user()->hasAnyPermission(['wo.execute', 'wo.assign', 'wo.cost_finalize']) && ! in_array($this->getOwnerRecord()->status, ['cancelled'], true))
                    ->using(function (array $data, CreateAction $action) {
                        $line = null;
                        F::attempt(function () use (&$line, $data) {
                            $line = app(WorkOrderService::class)->addCost($this->getOwnerRecord(), $data, auth()->user());
                        });
                        if (! $line) {
                            $action->halt();
                        }

                        return $line;
                    }),
            ])
            ->recordActions([
                DeleteAction::make()->visible(fn (WoCostLine $record) => $record->cost_status !== 'final' && $this->getOwnerRecord()->status !== 'closed'
                    && auth()->user()->hasAnyPermission(['wo.execute', 'wo.assign'])),
                Action::make('reversal')->label('Koreksi (reversal)')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(fn (WoCostLine $record) => $record->cost_status === 'final' && ! $record->reverses_id && auth()->user()->hasPermission('wo.cost_finalize'))
                    ->schema([Textarea::make('reason')->label('Alasan koreksi')->required()])
                    ->action(fn (WoCostLine $record, array $data) => F::attempt(fn () => app(WorkOrderService::class)->reverseCost($record, $data['reason'], auth()->user()), 'Baris reversal dibuat')),
            ]);
    }
}
