<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class RoomBlock extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'fo_confirmed_at' => 'datetime',
        ];
    }

    public function room(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'room_id');
    }

    public function workOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function proposer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function confirmer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'fo_confirmed_by');
    }

    public const STATUSES = ['proposed' => 'Diusulkan', 'confirmed' => 'Dikonfirmasi FO', 'rejected' => 'Ditolak FO', 'released' => 'Dilepas'];
}
