<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Status rencana PM per aset: terakhir dikerjakan, jatuh tempo berikutnya (tanggal / meter), WO yang sedang terbuka. */
class PmSchedule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_done_at' => 'date', 'next_due_at' => 'date', 'last_meter' => 'float', 'next_due_meter' => 'float'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PmPlan::class, 'pm_plan_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function openWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'open_work_order_id');
    }
}
