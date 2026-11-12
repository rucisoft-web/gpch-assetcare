<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SupplyRequestLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'issued_quantity' => 'float'];
    }

    protected static function booted(): void
    {
        // Baris anak tidak punya company_id sendiri; pastikan barangnya milik perusahaan yang sama.
        static::saving(function (SupplyRequestLine $line) {
            $requestCompany = SupplyRequest::withoutGlobalScopes()->whereKey($line->supply_request_id)->value('company_id');
            $itemCompany = SupplyItem::withoutGlobalScopes()->whereKey($line->supply_item_id)->value('company_id');

            if ((int) $requestCompany !== (int) $itemCompany) {
                throw new LogicException('Barang harus milik perusahaan yang sama dengan permintaannya.');
            }
        });
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class, 'supply_item_id');
    }
}
