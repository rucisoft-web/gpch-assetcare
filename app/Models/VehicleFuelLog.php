<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleFuelLog extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected array $companyParents = ['vehicle_id' => Vehicle::class];

    protected function casts(): array
    {
        return ['filled_at' => 'date', 'liters' => 'float', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(fn (VehicleFuelLog $log) => $log->recorded_by ??= auth()->id());
        static::saved(fn (VehicleFuelLog $log) => $log->vehicle->recordOdometer($log->odometer));
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
