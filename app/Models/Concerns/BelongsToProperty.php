<?php

namespace App\Models\Concerns;

use App\Models\Property;
use App\Support\CurrentProperty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain tenant: property_id diisi otomatis & query disaring ke properti aktif pengguna.
 * Relasi lintas properti ditolak oleh scope ini dan validasi service.
 */
trait BelongsToProperty
{
    public static function bootBelongsToProperty(): void
    {
        static::addGlobalScope('property', function (Builder $q) {
            if (auth()->check() && ($pid = app(CurrentProperty::class)->id())) {
                $q->where($q->getModel()->getTable().'.property_id', $pid);
            }
        });
        static::creating(function ($m) {
            $m->property_id ??= app(CurrentProperty::class)->id();
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
