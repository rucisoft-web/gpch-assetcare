<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetTransferItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'received_scan' => 'boolean',
        ];
    }

    public function transfer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AssetTransfer::class, 'transfer_id');
    }

    public function asset(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }
}
