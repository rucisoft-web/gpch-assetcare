<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Batch cetak label. PDF siap/diunduh ≠ sudah dicetak ≠ sudah terpasang (peristiwa terpisah). */
class LabelPrintBatch extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id'];

    public const STATUSES = ['ready' => 'PDF siap', 'downloaded' => 'Sudah diunduh', 'printed' => 'Cetak dikonfirmasi', 'cancelled' => 'Dibatalkan'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'generated_at' => 'datetime', 'downloaded_at' => 'datetime', 'printed_confirmed_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabelPrintItem::class, 'batch_id')->orderBy('sequence');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function printConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_confirmed_by');
    }

    public function profile(): array
    {
        return \App\Services\LabelService::profile($this->media_profile);
    }
}
