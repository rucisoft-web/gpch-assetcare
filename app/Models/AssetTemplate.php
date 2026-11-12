<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Templat aset per jenis ruang/lokasi, dipakai untuk membangkitkan daftar aset yang diharapkan. */
class AssetTemplate extends Model
{
    use BelongsToCompany;

    protected $attributes = ['is_active' => true, 'location_type' => 'room'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'location_type' => LocationType::class];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AssetTemplateLine::class)->orderBy('sort')->orderBy('id');
    }

    /** Lokasi yang memakai templat ini sebagai jenis ruangnya. */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
