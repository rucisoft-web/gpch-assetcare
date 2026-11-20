<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat work order')];
    }

    public function getTabs(): array
    {
        $uid = auth()->id();

        return [
            'saya' => Tab::make('Tugas saya')->modifyQueryUsing(fn ($query) => $query->whereIn('status', WorkOrder::OPEN)
                ->where(fn ($w) => $w->where('pic_id', $uid)->orWhereJsonContains('team_ids', $uid)->orWhereJsonContains('team_ids', (string) $uid))),
            'terbuka' => Tab::make('Terbuka')->modifyQueryUsing(fn ($query) => $query->whereIn('status', WorkOrder::OPEN)),
            'persetujuan' => Tab::make('Menunggu persetujuan')->modifyQueryUsing(fn ($query) => $query->where('status', 'awaiting_approval'))
                ->badge(fn () => WorkOrder::visibleTo(auth()->user())->where('status', 'awaiting_approval')->count() ?: null)->badgeColor('warning'),
            'verifikasi' => Tab::make('Menunggu verifikasi')->modifyQueryUsing(fn ($query) => $query->where('status', 'completed')),
            'overdue' => Tab::make('Melewati target')->modifyQueryUsing(fn ($query) => $query->whereIn('status', WorkOrder::OPEN)->where('due_at', '<', now()))
                ->badge(fn () => WorkOrder::visibleTo(auth()->user())->whereIn('status', WorkOrder::OPEN)->where('due_at', '<', now())->count() ?: null)->badgeColor('danger'),
            'pm' => Tab::make('Preventive & predictive')->modifyQueryUsing(fn ($query) => $query->whereIn('status', WorkOrder::OPEN)->whereIn('type', ['preventive', 'inspection', 'predictive'])),
            'semua' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return request('tab', auth()->user()?->hasPermission('wo.assign') ? 'terbuka' : 'saya');
    }
}
