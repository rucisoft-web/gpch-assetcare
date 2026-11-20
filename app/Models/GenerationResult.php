<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GenerationResult extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
        ];
    }

    public function batch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(GenerationBatch::class, 'batch_id');
    }

    public function room(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'room_id');
    }
}
