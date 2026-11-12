<?php

namespace App\Filament\App\Resources\Vehicles\RelationManagers;

use App\Models\VehicleFuelLog;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FuelLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'fuelLogs';

    protected static ?string $title = 'Pengisian BBM';

    public function isReadOnly(): bool
    {
        return auth()->user()?->roleIn($this->getOwnerRecord()->company_id)?->canOperate() !== true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filled_at')->label('Tanggal')->default(today())->required(),
            TextInput::make('odometer')->label('Odometer (km)')->numeric()->minValue(0)->default(fn () => $this->getOwnerRecord()->odometer),
            TextInput::make('liters')->label('Liter')->numeric()->minValue(0.1)->required(),
            TextInput::make('amount')->label('Biaya')->numeric()->prefix('Rp')->minValue(0)->required(),
            TextInput::make('station')->label('SPBU')->maxLength(255)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('filled_at', 'desc')
            ->columns([
                TextColumn::make('filled_at')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('odometer')->label('Odometer')->numeric(thousandsSeparator: '.')->suffix(' km')->placeholder('—'),
                TextColumn::make('liters')->label('Liter')->numeric(2, ',', '.')
                    ->summarize(Sum::make()->label('Total')->numeric(1, ',', '.')),
                TextColumn::make('amount')->label('Biaya')->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('price')->label('Rp/liter')
                    ->state(fn (VehicleFuelLog $record) => $record->liters > 0 ? Money::format($record->amount / $record->liters) : '—'),
                TextColumn::make('station')->label('SPBU')->placeholder('—'),
                TextColumn::make('recorder.name')->label('Dicatat oleh')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([CreateAction::make()->label('Catat pengisian')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
