<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Slot = posisi standar dalam kamar (mis. TV utama, lampu kiri). Aset = unit fisik permanen. */
class RoomAssetSlot extends Model
{
    use BelongsToProperty;

    protected $guarded = ['id'];

    public const STATUSES = ['empty' => 'Kosong', 'filled' => 'Terisi', 'not_available' => 'Tidak tersedia', 'archived' => 'Diarsipkan'];

    protected function casts(): array
    {
        return ['expected_qty' => 'decimal:2'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'room_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SlotAssignment::class, 'slot_id')->orderByDesc('assigned_at');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(SlotAssignment::class, 'slot_id')->whereNotNull('active_slot_key');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->standard_name.($this->position_label ? ' ('.$this->position_label.')' : '');
    }
}
