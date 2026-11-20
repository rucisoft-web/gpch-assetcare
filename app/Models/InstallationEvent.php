<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function label(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AssetLabel::class, 'asset_label_id');
    }

    public function asset(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function quantityGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AssetQuantityGroup::class, 'quantity_group_id');
    }

    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function printItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LabelPrintItem::class, 'print_item_id');
    }

    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public const STATUSES = ['pending' => 'Belum terpasang', 'installed' => 'Terpasang', 'damaged' => 'Label rusak', 'missing' => 'Label hilang', 'needs_relabel' => 'Perlu label ulang'];
}
