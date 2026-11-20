<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Daftarkan aset')];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'aktif' => Tab::make('Aktif')->modifyQueryUsing(fn ($query) => $query->where('lifecycle_status', 'active')),
            'draft' => Tab::make('Draft / kandidat')->modifyQueryUsing(fn ($query) => $query->where('lifecycle_status', 'draft'))
                ->badge(fn () => Asset::where('lifecycle_status', 'draft')->count() ?: null),
            'gangguan' => Tab::make('Tidak beroperasi')->modifyQueryUsing(fn ($query) => $query->whereIn('operational_status', ['under_repair', 'out_of_service'])),
            'berhenti' => Tab::make('Berhenti / dihapus')->modifyQueryUsing(fn ($query) => $query->whereIn('lifecycle_status', ['retired', 'disposed', 'lost'])),
        ];
    }
}
