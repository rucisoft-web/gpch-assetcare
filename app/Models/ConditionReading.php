<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionReading extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['normal' => 'Normal', 'warning' => 'Waspada', 'critical' => 'Kritis'];

    public const STATUS_COLORS = ['normal' => 'success', 'warning' => 'warning', 'critical' => 'danger'];

    protected function casts(): array
    {
        return ['value' => 'float', 'read_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ConditionParameter::class, 'condition_parameter_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function valueText(): string
    {
        return rtrim(rtrim(number_format($this->value, 3, ',', '.'), '0'), ',').($this->parameter?->unit ? ' '.$this->parameter->unit : '');
    }
}
