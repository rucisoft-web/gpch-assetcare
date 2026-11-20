<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kelas aset kebijakan penyusutan (LND, BLD, ME-H, FFE-W, ... CIP). */
class DepreciationClass extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_depreciable' => 'boolean',
            'is_intangible' => 'boolean',
            'is_active' => 'boolean',
            'residual_pct' => 'decimal:2',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(AssetCategory::class);
    }

    public function fiscalGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FiscalGroup::class, 'fiscal_group_code', 'code');
    }

    public function requiredDocumentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class, 'document_requirements')->withPivot('condition', 'is_mandatory')->withTimestamps();
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    public static function options(): array
    {
        return static::where('is_active', true)->orderBy('sort')->get()->mapWithKeys(fn ($c) => [$c->id => $c->code.' · '.$c->name])->all();
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' · '.$this->name;
    }

    public function lifeText(): string
    {
        if (! $this->is_depreciable || ! $this->commercial_life_months) {
            return 'Tidak disusutkan';
        }
        $y = $this->commercial_life_months / 12;

        return (fmod($y, 1.0) === 0.0 ? (int) $y.' tahun' : $this->commercial_life_months.' bulan').($this->residual_pct > 0 ? ', residu '.rtrim(rtrim((string) $this->residual_pct, '0'), '.').'%' : '');
    }
}
