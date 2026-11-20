<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/** Baris biaya WO. Final tidak diubah; koreksi memakai baris reversal terpisah. */
class WoCostLine extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public const TYPES = ['material' => 'Material', 'vendor' => 'Jasa vendor', 'labor' => 'Tenaga internal (alokasi)', 'other' => 'Biaya langsung lain'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'amount' => 'decimal:2', 'is_cash_out' => 'boolean', 'finalized_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $c) {
            if ($c->exists && $c->getOriginal('cost_status') === 'final' && $c->isDirty(['quantity', 'unit_cost', 'amount', 'type'])) {
                throw ValidationException::withMessages(['amount' => 'Biaya final tidak dapat diubah. Gunakan transaksi koreksi.']);
            }
            $c->amount = round((float) $c->quantity * (float) $c->unit_cost, 2);
            $c->is_cash_out = $c->type !== 'labor';
            $c->period ??= now()->timezone(config('assetcare.display_timezone'))->format('Y-m');
        });
        static::deleting(function (self $c) {
            if ($c->cost_status === 'final') {
                throw ValidationException::withMessages(['amount' => 'Biaya final tidak dapat dihapus. Gunakan transaksi koreksi.']);
            }
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
