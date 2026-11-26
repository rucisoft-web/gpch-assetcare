<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Parameter kondisi / meter untuk predictive maintenance dan PM berbasis meter. */
class ConditionParameter extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id'];

    public const DIRECTIONS = ['above' => 'Bahaya bila naik (≥ batas)', 'below' => 'Bahaya bila turun (≤ batas)'];

    protected function casts(): array
    {
        return ['is_meter' => 'boolean', 'wo_on_warning' => 'boolean', 'is_active' => 'boolean',
            'warning_value' => 'float', 'critical_value' => 'float'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(ConditionReading::class);
    }

    /** normal / warning / critical untuk nilai tertentu (meter tidak punya batas). */
    public function evaluate(float $value): string
    {
        $hit = fn (?float $limit) => $limit !== null && ($this->direction === 'below' ? $value <= $limit : $value >= $limit);
        if ($hit($this->critical_value)) {
            return 'critical';
        }

        return $hit($this->warning_value) ? 'warning' : 'normal';
    }

    public function limitText(): string
    {
        if ($this->is_meter) {
            return 'meter kumulatif';
        }
        $op = $this->direction === 'below' ? '≤' : '≥';
        $u = $this->unit ? ' '.$this->unit : '';
        $parts = [];
        if ($this->warning_value !== null) {
            $parts[] = 'waspada '.$op.' '.rtrim(rtrim(number_format($this->warning_value, 3, ',', '.'), '0'), ',').$u;
        }
        if ($this->critical_value !== null) {
            $parts[] = 'kritis '.$op.' '.rtrim(rtrim(number_format($this->critical_value, 3, ',', '.'), '0'), ',').$u;
        }

        return $parts ? implode(' · ', $parts) : 'tanpa batas';
    }

    /** Parameter yang berlaku untuk aset (umum atau sesuai kategorinya). */
    public static function forAsset(Asset $a)
    {
        return static::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('category_id')->orWhere('category_id', $a->category_id))->orderBy('name')->get();
    }
}
