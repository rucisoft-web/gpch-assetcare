<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rencana preventive maintenance: per aset atau per kategori, interval hari dan/atau meter. */
class PmPlan extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id'];

    public const SCOPES = ['asset' => 'Satu aset', 'category' => 'Semua aset dalam kategori'];

    public const WO_TYPES = ['preventive' => 'Preventive maintenance', 'inspection' => 'Inspeksi'];

    protected function casts(): array
    {
        return ['checklist' => 'array', 'is_active' => 'boolean', 'needs_vendor' => 'boolean', 'starts_on' => 'date', 'interval_meter' => 'float'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function meterParameter(): BelongsTo
    {
        return $this->belongsTo(ConditionParameter::class, 'meter_parameter_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PmSchedule::class);
    }

    /** Aset yang tercakup rencana ini (draft/aktif). */
    public function targetAssets(): Builder
    {
        return Asset::query()->whereIn('lifecycle_status', ['draft', 'active'])
            ->when($this->scope === 'asset', fn ($q) => $q->whereKey($this->asset_id))
            ->when($this->scope === 'category', fn ($q) => $q->where('category_id', $this->category_id));
    }

    public function intervalText(): string
    {
        $p = [];
        if ($this->interval_days) {
            $d = $this->interval_days;
            $p[] = match (true) {
                $d % 365 === 0 => ($d / 365 === 1 ? 'tahunan' : 'tiap '.($d / 365).' tahun'),
                $d % 30 === 0 => ($d / 30 === 1 ? 'bulanan' : 'tiap '.($d / 30).' bulan'),
                $d % 7 === 0 => ($d / 7 === 1 ? 'mingguan' : 'tiap '.($d / 7).' minggu'),
                default => 'tiap '.$d.' hari',
            };
        }
        if ($this->interval_meter && $this->meterParameter) {
            $p[] = 'tiap '.rtrim(rtrim(number_format($this->interval_meter, 3, ',', '.'), '0'), ',').' '.($this->meterParameter->unit ?: $this->meterParameter->name);
        }

        return $p ? implode(' atau ', $p) : '—';
    }
}
