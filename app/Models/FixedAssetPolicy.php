<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use App\Support\CurrentProperty;
use Illuminate\Database\Eloquent\Model;

/** Angka kebijakan penyusutan yang ditetapkan FC (batas kapitalisasi, umur fiskal bangunan, tarif pajak). */
class FixedAssetPolicy extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id'];

    public const STATUSES = ['draft' => 'Draf (belum disahkan FC)', 'approved' => 'Disahkan FC'];

    protected function casts(): array
    {
        return [
            'capitalisation_threshold' => 'decimal:2',
            'group_purchase_threshold' => 'decimal:2',
            'renovation_threshold' => 'decimal:2',
            'deferred_tax_rate' => 'decimal:2',
            'effective_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /** Kebijakan properti aktif; dibuat dengan nilai usulan bila belum ada. */
    public static function current(?int $propertyId = null): self
    {
        $pid = $propertyId ?? app(CurrentProperty::class)->id() ?? Property::query()->value('id');

        return static::withoutGlobalScopes()->firstOrCreate(['property_id' => $pid]);
    }

    public function auditLabel(): string
    {
        return 'Kebijakan aset tetap';
    }
}
