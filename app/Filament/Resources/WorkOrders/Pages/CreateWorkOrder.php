<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Services\SlaService;
use App\Services\WorkOrderService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $t = app(SlaService::class)->targets($data['priority'], now());
            $wo = static::getModel()::create($data + [
                'status' => 'draft', 'received_at' => now(), 'response_due_at' => $t['response_due_at'], 'due_at' => $t['due_at'],
                'sla_calendar' => $t['calendar'], 'is_emergency' => $data['priority'] === 'P1',
            ]);
            app(WorkOrderService::class)->event($wo, null, 'draft', 'create', 'Dibuat langsung');

            return $wo;
        });
    }

    protected function getRedirectUrl(): string
    {
        return WorkOrderResource::getUrl('view', ['record' => $this->record]);
    }
}
