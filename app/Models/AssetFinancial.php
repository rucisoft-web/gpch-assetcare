<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AssetFinancial extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'replacement_estimate' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'depreciation_start_date' => 'date',
            'fiscal_start_date' => 'date',
            'opening_balance_date' => 'date',
            'opening_accum_commercial' => 'decimal:2',
            'opening_accum_fiscal' => 'decimal:2',
            'impairment_loss' => 'decimal:2',
            'disposal_date' => 'date',
            'disposal_proceeds' => 'decimal:2',
        ];
    }

    public const CAPITALISATION = [
        'capitalised' => 'Dikapitalisasi (per unit)',
        'group' => 'Dikapitalisasi sebagai kelompok',
        'expensed' => 'Dibebankan (di bawah batas)',
        'pending' => 'Menunggu keputusan FC',
    ];

    public function depreciationClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DepreciationClass::class);
    }

    public function asset(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
