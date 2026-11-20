<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Laporkan kerusakan')->icon('heroicon-o-plus')];
    }

    public function getTabs(): array
    {
        return [
            'terbuka' => Tab::make('Terbuka')->modifyQueryUsing(fn ($query) => $query->whereIn('status', ServiceRequest::OPEN)),
            'baru' => Tab::make('Belum ditriase')->modifyQueryUsing(fn ($query) => $query->where('status', 'submitted'))
                ->badge(fn () => ServiceRequest::visibleTo(auth()->user())->where('status', 'submitted')->count() ?: null)->badgeColor('danger'),
            'saya' => Tab::make('Laporan saya')->modifyQueryUsing(fn ($query) => $query->where('reporter_id', auth()->id())),
            'semua' => Tab::make('Semua'),
        ];
    }
}
