<?php

namespace App\Filament\Resources\AssetTransfers\Pages;

use App\Filament\Resources\AssetTransfers\AssetTransferResource;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Services\TransferService;
use App\Services\WorkflowException;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateAssetTransfer extends CreateRecord
{
    protected static string $resource = AssetTransferResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $t = DB::transaction(function () use ($data) {
            $assets = Asset::whereIn('id', $data['asset_ids'])->lockForUpdate()->get();
            if ($busy = $assets->firstWhere('in_transit', true)) {
                throw WorkflowException::invalid($busy->asset_code.' sedang dalam mutasi lain.');
            }
            $from = $assets->pluck('location_id')->unique();
            $t = AssetTransfer::create([
                'from_location_id' => $from->count() === 1 ? $from->first() : null, 'to_location_id' => $data['to_location_id'],
                'to_department_id' => $data['to_department_id'] ?? null, 'reason' => $data['reason'], 'status' => 'draft', 'requested_by' => auth()->id(),
            ]);
            foreach ($assets as $a) {
                $t->items()->create(['asset_id' => $a->id, 'from_location_id' => $a->location_id, 'condition_out' => $a->condition_grade]);
            }

            return $t;
        });
        if ($data['submit_now'] ?? false) {
            app(TransferService::class)->submit($t, auth()->user());
        }

        return $t;
    }

    protected function getRedirectUrl(): string
    {
        return AssetTransferResource::getUrl('view', ['record' => $this->record]);
    }
}
