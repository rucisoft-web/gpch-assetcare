<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Support\F;
use App\Models\Asset;
use App\Services\AssetService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Komponen dengan riwayat terpisah (mis. compressor pada unit AC). Siklus induk dicegah. */
class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Komponen';

    public function table(Table $table): Table
    {
        $search = fn (string $s) => Asset::where('id', '!=', $this->getOwnerRecord()->id)
            ->where(fn ($q) => $q->where('asset_code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"))->limit(30)->get()->mapWithKeys(fn ($a) => [$a->id => $a->label]);

        return $table
            ->columns([
                TextColumn::make('asset_code')->label('Kode')->fontFamily('mono'),
                TextColumn::make('name')->label('Komponen'),
                TextColumn::make('serial_number')->label('No. seri')->placeholder('—'),
                TextColumn::make('condition_grade')->label('Kondisi')->placeholder('?'),
            ])
            ->headerActions([
                Action::make('pasang')->label('Pasang komponen')->icon('heroicon-o-plus')
                    ->visible(fn () => auth()->user()?->hasPermission('asset.edit'))
                    ->schema([
                        Select::make('component_id')->label('Aset komponen')->searchable()->required()->getSearchResultsUsing($search)->getOptionLabelUsing(fn ($value) => Asset::find($value)?->label),
                        Select::make('replaces_id')->label('Menggantikan komponen (opsional)')->native(false)
                            ->options(fn () => $this->getOwnerRecord()->children()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label])),
                        TextInput::make('work_order_id')->label('ID work order terkait (opsional)')->numeric(),
                        TextInput::make('reason')->label('Alasan'),
                    ])
                    ->action(fn (array $data) => F::attempt(fn () => app(AssetService::class)->attachComponent(
                        $this->getOwnerRecord(), Asset::findOrFail($data['component_id']), $data['reason'] ?? null, auth()->user(),
                        $data['work_order_id'] ?? null, ! empty($data['replaces_id']) ? Asset::find($data['replaces_id']) : null,
                    ), 'Komponen dipasang')),
            ])
            ->recordActions([
                Action::make('buka')->label('Kartu')->url(fn ($record) => AssetResource::getUrl('view', ['record' => $record])),
                Action::make('lepas')->label('Lepas')->color('warning')->visible(fn () => auth()->user()?->hasPermission('asset.edit'))
                    ->schema([TextInput::make('reason')->label('Alasan')->required()])
                    ->action(fn ($record, array $data) => F::attempt(fn () => app(AssetService::class)->detachComponent($record, $data['reason'], auth()->user()), 'Komponen dilepas')),
            ]);
    }
}
