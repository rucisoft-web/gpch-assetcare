<?php

namespace App\Models;

use App\Enums\VehicleServiceType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat servis kendaraan. Jadwal servis berikutnya ikut memperbarui data kendaraan. */
class VehicleService extends Model
{
    use Auditable, BelongsToCompany;

    protected $attributes = ['service_type' => 'routine', 'cost' => 0];

    protected $guarded = ['id'];

    protected array $companyParents = ['vehicle_id' => Vehicle::class];

    protected function casts(): array
    {
        return ['service_type' => VehicleServiceType::class, 'service_date' => 'date', 'next_service_date' => 'date', 'cost' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saved(function (VehicleService $service) {
            $vehicle = $service->vehicle;
            $vehicle->recordOdometer($service->odometer);

            $isLatest = ! $vehicle->services()->whereKeyNot($service->id)->whereDate('service_date', '>', $service->service_date)->exists();

            if ($isLatest && ($service->next_service_date || $service->next_service_km)) {
                $vehicle->update(array_filter([
                    'next_service_date' => $service->next_service_date,
                    'next_service_km' => $service->next_service_km,
                ]));
            }
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function auditLabel(): string
    {
        return $this->vehicle?->plate_number.' '.$this->service_type->getLabel();
    }
}
