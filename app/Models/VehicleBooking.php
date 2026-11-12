<?php

namespace App\Models;

use App\Enums\VehicleBookingState;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Sequence;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Pemesanan kendaraan dinas: diajukan → disetujui (kendaraan ditetapkan) → berangkat → kembali. */
class VehicleBooking extends Model
{
    use Auditable, BelongsToCompany;

    /** Status yang menahan kendaraan. */
    public const HOLDING_STATES = ['approved', 'in_use'];

    public const OPEN_STATES = ['requested', 'approved', 'in_use'];

    protected $attributes = ['state' => 'requested', 'passengers' => 1];

    protected $guarded = ['id'];

    protected array $companyParents = ['vehicle_id' => Vehicle::class, 'department_id' => Department::class];

    protected array $tracked = ['state', 'vehicle_id', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['state' => VehicleBookingState::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (VehicleBooking $booking) {
            $booking->number ??= Sequence::next('vehicle_booking', $booking->company_id);
            $booking->requester_id ??= auth()->id();
        });

        static::saving(function (VehicleBooking $booking) {
            if ($booking->ends_at->lte($booking->starts_at)) {
                throw new LogicException('Waktu kembali harus setelah waktu berangkat.');
            }

            if (in_array($booking->state->value, self::HOLDING_STATES, true)
                && $booking->isDirty(['vehicle_id', 'starts_at', 'ends_at', 'state'])
                && ($conflict = static::conflictFor($booking->vehicle_id, $booking->starts_at, $booking->ends_at, $booking->id))) {
                throw new LogicException(static::conflictMessage($conflict));
            }
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('state', self::OPEN_STATES);
    }

    /** Pemesanan lain yang sudah memegang kendaraan ini pada waktu yang beririsan. */
    public static function conflictFor(?int $vehicleId, ?CarbonInterface $start, ?CarbonInterface $end, ?int $ignoreId = null): ?self
    {
        if (! $vehicleId || ! $start || ! $end) {
            return null;
        }

        return static::query()
            ->whereIn('state', self::HOLDING_STATES)
            ->where('vehicle_id', $vehicleId)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->orderBy('starts_at')
            ->first();
    }

    public static function conflictMessage(self $conflict): string
    {
        return sprintf('Kendaraan sudah dipakai %s %s–%s (%s).',
            $conflict->starts_at->translatedFormat('d M'), $conflict->starts_at->format('H:i'), $conflict->ends_at->translatedFormat('d M H:i'), $conflict->number);
    }

    public function approve(int $vehicleId, ?string $driver = null): void
    {
        $this->ensureState(VehicleBookingState::Requested);
        $this->update([
            'state' => VehicleBookingState::Approved,
            'vehicle_id' => $vehicleId,
            'driver_name' => $driver ?: $this->driver_name ?: Vehicle::find($vehicleId)?->default_driver,
            'approved_by' => auth()->id(),
        ]);
    }

    public function reject(?string $note = null): void
    {
        $this->ensureState(VehicleBookingState::Requested);
        $this->update(['state' => VehicleBookingState::Rejected, 'approved_by' => auth()->id(), 'note' => $note]);
    }

    public function depart(?int $odometer = null): void
    {
        $this->ensureState(VehicleBookingState::Approved);
        $this->update(['state' => VehicleBookingState::InUse, 'odometer_start' => $odometer ?? $this->vehicle?->odometer]);
    }

    public function complete(int $odometer): void
    {
        $this->ensureState(VehicleBookingState::InUse);

        if ($this->odometer_start && $odometer < $this->odometer_start) {
            throw new LogicException('Odometer kembali tidak boleh lebih kecil dari odometer berangkat.');
        }

        $this->update([
            'state' => VehicleBookingState::Returned,
            'odometer_end' => $odometer,
            'distance_km' => $this->odometer_start ? $odometer - $this->odometer_start : null,
        ]);
        $this->vehicle?->recordOdometer($odometer);
    }

    public function cancel(): void
    {
        $this->ensureState(VehicleBookingState::Requested, VehicleBookingState::Approved);
        $this->update(['state' => VehicleBookingState::Cancelled]);
    }

    protected function ensureState(VehicleBookingState ...$states): void
    {
        if (! in_array($this->state, $states, true)) {
            throw new LogicException("Pemesanan berstatus {$this->state->getLabel()} tidak bisa diproses.");
        }
    }

    public function auditLabel(): string
    {
        return (string) $this->number;
    }
}
