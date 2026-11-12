<?php

namespace App\Filament\App\Resources\SupplyItems\Pages;

use App\Enums\StockMovementType;
use App\Filament\App\Resources\SupplyItems\SupplyItemResource;
use App\Models\SupplyItem;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageSupplyItems extends ManageRecords
{
    protected static string $resource = SupplyItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Stok awal dicatat sebagai mutasi masuk agar riwayat stok lengkap sejak hari pertama.
            CreateAction::make()->using(function (array $data) {
                $opening = (float) ($data['opening_stock'] ?? 0);
                $record = SupplyItem::create(collect($data)->except('opening_stock')->all());

                if ($opening > 0) {
                    $record->move(StockMovementType::In, $opening, ['reference' => 'Stok awal', 'unit_cost' => $record->unit_cost]);
                }

                return $record;
            }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua barang'),
            'menipis' => Tab::make('Stok menipis')->modifyQueryUsing(fn (Builder $query) => $query->lowStock())
                ->badge(fn () => SupplyItem::query()->lowStock()->count() ?: null)->badgeColor('danger'),
        ];
    }
}
