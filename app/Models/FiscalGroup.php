<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Kelompok harta fiskal (UU PPh Pasal 11, PMK 72/2023). Metode garis lurus untuk semua kelompok. */
class FiscalGroup extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /** Bangunan permanen: umur mengikuti pilihan kebijakan (20 tahun, atau umur sesuai buku). */
    public const BUILDING = 'BP';

    public static function lifeFor(?string $code, ?FixedAssetPolicy $policy = null): ?int
    {
        if (! $code || $code === 'NONE') {
            return null;
        }
        if ($code === self::BUILDING) {
            return (int) ($policy ?? FixedAssetPolicy::current())->building_fiscal_life_years * 12;
        }

        return once(fn () => static::pluck('life_months', 'code')->all())[$code] ?? null;
    }

    public static function options(): array
    {
        return static::orderBy('sort')->get()->mapWithKeys(fn ($g) => [$g->code => $g->code.' · '.$g->name])->all();
    }

    public function getRateAttribute(): ?float
    {
        return $this->life_months ? round(1200 / $this->life_months, 2) : null;
    }
}
