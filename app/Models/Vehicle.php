<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Vehicle extends Model
{
    use Auditable, BelongsToCompany;

    /** Tanggal jatuh tempo yang dipantau: kolom => label. */
    public const DUE_DATES = [
        'tax_due_date' => 'Pajak tahunan',
        'stnk_due_date' => 'Perpanjangan STNK',
        'kir_due_date' => 'Uji KIR',
        'insurance_due_date' => 'Asuransi',
        'next_service_date' => 'Servis berkala',
    ];

    protected $attributes = ['status' => 'available', 'type' => 'car', 'odometer' => 0];

    protected $guarded = ['id'];

    protected array $companyParents = ['asset_id' => Asset::class];

    protected array $tracked = ['status', 'odometer', 'tax_due_date', 'stnk_due_date', 'next_service_date'];

    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'status' => VehicleStatus::class,
            'tax_due_date' => 'date',
            'stnk_due_date' => 'date',
            'kir_due_date' => 'date',
            'insurance_due_date' => 'date',
            'next_service_date' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(VehicleBooking::class);
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(VehicleFuelLog::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(VehicleService::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LegalDocument::class);
    }

    /** Kendaraan yang punya jatuh tempo (pajak, STNK, KIR, asuransi, servis) sebelum batas tanggal. */
    public function scopeDueBefore(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where(function (Builder $q) use ($date) {
            foreach (array_keys(self::DUE_DATES) as $column) {
                $q->orWhereDate($column, '<=', $date);
            }
        });
    }

    /** @return Collection<int, array{label: string, date: CarbonInterface}> jatuh tempo kendaraan ini sebelum batas tanggal. */
    public function dueItems(CarbonInterface $until): Collection
    {
        return collect(self::DUE_DATES)
            ->map(fn (string $label, string $column) => ['label' => $label, 'date' => $this->{$column}])
            ->filter(fn (array $item) => $item['date'] && $item['date']->lte($until))
            ->sortBy('date')
            ->values();
    }

    public function recordOdometer(?int $km): void
    {
        if ($km && $km > $this->odometer) {
            $this->update(['odometer' => $km]);
        }
    }

    public function auditLabel(): string
    {
        return "{$this->plate_number} {$this->name}";
    }
}
