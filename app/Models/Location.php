<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/** Lokasi berjenjang, misalnya Gedung / Lantai 3 / Kamar 301. */
class Location extends Model
{
    use BelongsToCompany;

    protected $attributes = ['is_active' => true];

    protected $guarded = ['id'];

    protected array $companyParents = ['parent_id' => Location::class, 'asset_template_id' => AssetTemplate::class];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Location $location) {
            if ($location->exists && $location->parent_id && in_array($location->parent_id, [$location->id, ...$location->descendantIds()], true)) {
                throw new InvalidArgumentException('Lokasi induk tidak boleh lokasi itu sendiri atau turunannya.');
            }

            $parent = $location->parent_id ? static::withoutGlobalScopes()->find($location->parent_id) : null;
            $location->complete_name = $parent ? $parent->complete_name.' / '.$location->name : $location->name;
        });

        // Perbarui nama lengkap turunan bila nama atau induk berubah.
        static::saved(function (Location $location) {
            if ($location->wasChanged(['name', 'parent_id', 'complete_name'])) {
                $location->children()->withoutGlobalScopes()->get()->each->save();
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /** Jenis ruang: templat aset yang berlaku untuk lokasi ini. */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AssetTemplate::class, 'asset_template_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return array<int> */
    public function descendantIds(): array
    {
        $ids = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = static::withoutGlobalScopes()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
