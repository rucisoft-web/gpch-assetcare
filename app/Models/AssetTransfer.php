<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AssetTransfer extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id', 'number'];

    protected $attributes = ['version' => 1];

    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Diajukan', 'approved' => 'Disetujui', 'dispatched' => 'Dalam perjalanan', 'received' => 'Diterima', 'closed' => 'Selesai', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'dispatched_at' => 'datetime', 'received_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->number ??= Sequence::next('MUT', $t->property_id));
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetTransferItem::class, 'transfer_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function statusEvents(): MorphMany
    {
        return $this->morphMany(StatusEvent::class, 'subject')->orderByDesc('occurred_at');
    }
}
