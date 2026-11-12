<?php

namespace App\Filament\App\Resources\SupplyRequests\Pages;

use App\Filament\App\Resources\SupplyRequests\SupplyRequestResource;
use App\Models\SupplyRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSupplyRequests extends ListRecords
{
    protected static string $resource = SupplyRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat permintaan')];
    }

    public function getTabs(): array
    {
        return [
            'proses' => Tab::make('Perlu diproses')->modifyQueryUsing(fn (Builder $query) => $query->open()),
            'semua' => Tab::make('Semua'),
        ];
    }
}
