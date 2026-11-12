<?php

namespace App\Filament\App\Resources\Vehicles\RelationManagers;

use App\Enums\VehicleServiceType;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $title = 'Riwayat Servis';

    public function isReadOnly(): bool
    {
        return auth()->user()?->roleIn($this->getOwnerRecord()->company_id)?->canOperate() !== true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('service_date')->label('Tanggal servis')->default(today())->required(),
            Select::make('service_type')->label('Jenis')->options(VehicleServiceType::class)->default(VehicleServiceType::Routine)->required(),
            TextInput::make('odometer')->label('Odometer (km)')->numeric()->minValue(0)->default(fn () => $this->getOwnerRecord()->odometer),
            TextInput::make('workshop')->label('Bengkel')->maxLength(255),
            TextInput::make('cost')->label('Biaya')->numeric()->prefix('Rp')->minValue(0)->default(0),
            Textarea::make('description')->label('Pekerjaan')->placeholder('Ganti oli mesin, filter oli, cek rem')->columnSpanFull(),
            DatePicker::make('next_service_date')->label('Servis berikutnya')->helperText('Memperbarui jadwal servis kendaraan.'),
            TextInput::make('next_service_km')->label('Servis berikutnya di km')->numeric(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('service_date', 'desc')
            ->columns([
                TextColumn::make('service_date')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('service_type')->label('Jenis')->badge(),
                TextColumn::make('description')->label('Pekerjaan')->wrap()->limit(80)->placeholder('—'),
                TextColumn::make('workshop')->label('Bengkel')->placeholder('—'),
                TextColumn::make('odometer')->label('Odometer')->numeric(thousandsSeparator: '.')->suffix(' km')->placeholder('—'),
                TextColumn::make('cost')->label('Biaya')->formatStateUsing(fn ($state) => Money::format($state))
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('next_service_date')->label('Berikutnya')->date('d/m/Y')->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()->label('Catat servis')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
