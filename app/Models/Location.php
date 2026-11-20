<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Location extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id'];

    public const TYPES = [
        'building' => 'Gedung', 'zone' => 'Zona', 'floor' => 'Lantai', 'room' => 'Kamar',
        'venue' => 'Venue / ruang rapat', 'area' => 'Area / ruang', 'point' => 'Titik pemasangan',
    ];

    public const PMS_STATUSES = ['vacant' => 'Vacant', 'occupied' => 'Occupied', 'out_of_order' => 'Out of order', 'out_of_service' => 'Out of service'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Location $l) {
            // cegah hierarki siklik
            $seen = [$l->id];
            $p = $l->parent_id ? Location::withoutGlobalScopes()->find($l->parent_id) : null;
            while ($p) {
                if (in_array($p->id, $seen, true)) {
                    throw ValidationException::withMessages(['parent_id' => 'Induk lokasi membentuk siklus.']);
                }
                if ($l->property_id && (int) $p->property_id !== (int) $l->property_id) {
                    throw ValidationException::withMessages(['parent_id' => 'Induk lokasi harus pada properti yang sama.']);
                }
                $seen[] = $p->id;
                $p = $p->parent_id ? Location::withoutGlobalScopes()->find($p->parent_id) : null;
            }
            $l->sort_key = $l->computeSortKey();
            $l->full_path = $l->computePath();
        });
        static::saved(function (Location $l) {
            if ($l->wasChanged(['name', 'parent_id'])) {
                $l->children()->get()->each->save(); // perbarui path anak
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(RoomAssetSlot::class, 'room_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(RoomException::class, 'room_id');
    }

    public function quantityGroups(): HasMany
    {
        return $this->hasMany(AssetQuantityGroup::class);
    }

    public function ancestors(): array
    {
        $out = [];
        $p = $this->parent_id ? Location::withoutGlobalScopes()->find($this->parent_id) : null;
        while ($p && count($out) < 10) {
            array_unshift($out, $p);
            $p = $p->parent_id ? Location::withoutGlobalScopes()->find($p->parent_id) : null;
        }

        return $out;
    }

    public function computePath(): string
    {
        return collect($this->ancestors())->pluck('name')->push($this->name)->join(' › ');
    }

    /** Urutan natural gedung → lantai → nomor kamar (angka dipad nol). */
    public function computeSortKey(): string
    {
        $parts = collect($this->ancestors())->push($this)->map(fn ($l) => preg_replace_callback('/\d+/', fn ($m) => str_pad($m[0], 6, '0', STR_PAD_LEFT), (string) $l->code));

        return mb_substr($parts->join('/'), 0, 60);
    }

    public function floor(): ?Location
    {
        return collect($this->ancestors())->reverse()->first(fn ($l) => $l->location_type === 'floor');
    }

    public function isRoom(): bool
    {
        return in_array($this->location_type, ['room', 'venue'], true);
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' · '.$this->name;
    }

    /** Teks lokasi pada label (snapshot saat cetak). */
    public function labelText(): string
    {
        return $this->location_type === 'room' ? 'Kamar '.($this->room_ref ?: $this->name) : $this->name;
    }

    /** @return array<int,string> */
    public static function options(?array $types = null): array
    {
        return static::query()->where('is_active', true)
            ->when($types, fn ($q) => $q->whereIn('location_type', $types))
            ->orderBy('sort_key')->get()->mapWithKeys(fn ($l) => [$l->id => $l->full_path ?: $l->name])->all();
    }
}
