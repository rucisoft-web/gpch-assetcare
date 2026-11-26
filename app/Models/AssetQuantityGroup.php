<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Kelompok kuantitas per lokasi (gelas, linen, kursi sejenis) — terpisah dari aset individual. */
class AssetQuantityGroup extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id', 'group_code'];

    public const STATUSES = ['draft' => 'Draft', 'active' => 'Aktif', 'retired' => 'Berhenti'];

    protected function casts(): array
    {
        return ['on_hand' => 'decimal:2', 'expected_qty' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $g) => $g->group_code ??= Sequence::groupCode($g->property_id));
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(QuantityMovement::class, 'quantity_group_id')->orderByDesc('occurred_at');
    }

    public function activeLabel(): HasOne
    {
        return $this->hasOne(AssetLabel::class, 'quantity_group_id')->where('status', 'active');
    }

    /** Catat hitung aktual (formulir hitung dari QR kelompok). */
    public function recordCount(float $qty, ?string $reason = null, string $type = 'count'): void
    {
        DB::transaction(function () use ($qty, $reason, $type) {
            $g = static::whereKey($this->id)->lockForUpdate()->first();
            QuantityMovement::create([
                'quantity_group_id' => $g->id, 'type' => $type, 'qty_before' => $g->on_hand, 'qty_after' => $qty,
                'reason' => $reason, 'actor_id' => Auth::id(), 'occurred_at' => now(),
            ]);
            $g->update(['on_hand' => $qty]);
            $this->refresh();
        });
    }

    public function getLabelAttribute(): string
    {
        return $this->group_code.' · '.$this->name;
    }
}
