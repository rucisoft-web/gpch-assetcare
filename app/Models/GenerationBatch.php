<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationBatch extends Model
{
    use BelongsToProperty;

    protected $guarded = ['id'];

    public const STATUSES = ['previewed' => 'Preview', 'committing' => 'Diproses', 'completed' => 'Selesai', 'partial' => 'Sebagian gagal', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan'];

    protected function casts(): array
    {
        return ['room_ids' => 'array', 'preview_snapshot' => 'array', 'match_decisions' => 'array', 'committed_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RoomAssetTemplate::class, 'template_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(GenerationResult::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
