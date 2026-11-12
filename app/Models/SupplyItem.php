<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Enums\SupplyCategory;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Barang persediaan bagian umum (ATK, kebersihan, pantry). Stok hanya berubah lewat mutasi. */
class SupplyItem extends Model
{
    use Auditable, BelongsToCompany;

    protected $attributes = ['is_active' => true, 'category' => 'stationery', 'unit' => 'pcs', 'stock' => 0, 'min_stock' => 0, 'unit_cost' => 0];

    protected $guarded = ['id'];

    protected array $tracked = ['min_stock', 'unit_cost', 'is_active'];

    protected function casts(): array
    {
        return ['category' => SupplyCategory::class, 'stock' => 'float', 'min_stock' => 'float', 'unit_cost' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock');
    }

    public function isLow(): bool
    {
        return $this->min_stock > 0 && $this->stock <= $this->min_stock;
    }

    /** Catat mutasi stok; jumlah selalu positif kecuali penyesuaian (boleh negatif). */
    public function move(StockMovementType $type, float $quantity, array $attributes = []): StockMovement
    {
        return $this->movements()->create($attributes + [
            'company_id' => $this->company_id,
            'type' => $type,
            'quantity' => $quantity,
        ]);
    }
}
