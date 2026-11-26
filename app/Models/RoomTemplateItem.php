<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class RoomTemplateItem extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RoomAssetTemplate::class, 'template_id');
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class, 'default_department_id');
    }

    /** @return array<int,string> label posisi per unit (1..quantity) */
    public function positionLabels(): array
    {
        $labels = array_values(array_filter(array_map('trim', explode(',', (string) $this->positions)), fn ($v) => $v !== ''));
        $out = [];
        for ($i = 1; $i <= max(1, (int) $this->quantity); $i++) {
            $out[$i] = $labels[$i - 1] ?? ($this->quantity > 1 ? 'Unit '.$i : null);
        }

        return $out;
    }
}
