<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Mutasi stok. Tidak bisa diubah; koreksi dicatat sebagai penyesuaian baru. */
class StockMovement extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['supply_item_id' => SupplyItem::class, 'supply_request_id' => SupplyRequest::class];

    protected function casts(): array
    {
        return ['type' => StockMovementType::class, 'quantity' => 'float', 'stock_after' => 'float', 'moved_at' => 'date', 'unit_cost' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement) {
            $movement->moved_at ??= today();
            $movement->user_id ??= auth()->id();

            if ($movement->quantity == 0 || ($movement->type !== StockMovementType::Adjust && $movement->quantity < 0)) {
                throw new LogicException('Jumlah mutasi harus lebih dari nol.');
            }

            DB::transaction(function () use ($movement) {
                $item = SupplyItem::query()->whereKey($movement->supply_item_id)->lockForUpdate()->firstOrFail();
                $delta = $movement->type === StockMovementType::Out ? -$movement->quantity : $movement->quantity;
                $after = round($item->stock + $delta, 2);

                if ($after < 0) {
                    throw new LogicException("Stok {$item->name} tidak cukup (tersisa ".rtrim(rtrim(number_format($item->stock, 2, ',', '.'), '0'), ',')." {$item->unit}).");
                }

                $changes = ['stock' => $after];

                // Harga satuan mengikuti pembelian terakhir.
                if ($movement->type === StockMovementType::In && $movement->unit_cost > 0) {
                    $changes['unit_cost'] = $movement->unit_cost;
                }

                $item->update($changes);
                $movement->stock_after = $after;
            });
        });

        static::updating(fn () => throw new LogicException('Mutasi stok tidak bisa diubah. Catat penyesuaian baru.'));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class, 'supply_item_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
