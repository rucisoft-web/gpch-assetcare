<?php

namespace App\Models;

use App\Enums\RoomBookingState;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Sequence;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Pemesanan ruang rapat. Jadwal yang bentrok dengan pemesanan aktif ditolak. */
class RoomBooking extends Model
{
    use Auditable, BelongsToCompany;

    /** Status yang menahan slot ruangan. */
    public const ACTIVE_STATES = ['requested', 'approved'];

    protected $attributes = ['state' => 'requested', 'attendees' => 1];

    protected $guarded = ['id'];

    protected array $companyParents = ['meeting_room_id' => MeetingRoom::class, 'department_id' => Department::class];

    protected array $tracked = ['state', 'starts_at', 'ends_at', 'meeting_room_id'];

    protected function casts(): array
    {
        return ['state' => RoomBookingState::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (RoomBooking $booking) {
            $booking->number ??= Sequence::next('room_booking', $booking->company_id);
            $booking->organizer_id ??= auth()->id();
        });

        static::saving(function (RoomBooking $booking) {
            if ($booking->ends_at->lte($booking->starts_at)) {
                throw new LogicException('Jam selesai harus setelah jam mulai.');
            }

            if (in_array($booking->state->value, self::ACTIVE_STATES, true)
                && $booking->isDirty(['meeting_room_id', 'starts_at', 'ends_at', 'state'])
                && ($conflict = static::conflictFor($booking->meeting_room_id, $booking->starts_at, $booking->ends_at, $booking->id))) {
                throw new LogicException(static::conflictMessage($conflict));
            }
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('state', self::ACTIVE_STATES);
    }

    /** Pemesanan aktif lain di ruangan yang sama yang waktunya beririsan. */
    public static function conflictFor(?int $roomId, ?CarbonInterface $start, ?CarbonInterface $end, ?int $ignoreId = null): ?self
    {
        if (! $roomId || ! $start || ! $end) {
            return null;
        }

        return static::query()->active()
            ->where('meeting_room_id', $roomId)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->orderBy('starts_at')
            ->first();
    }

    public static function conflictMessage(self $conflict): string
    {
        return sprintf('Ruangan sudah dipesan %s pukul %s–%s (%s).',
            $conflict->starts_at->translatedFormat('d M'), $conflict->starts_at->format('H:i'), $conflict->ends_at->format('H:i'), $conflict->title);
    }

    public function approve(): void
    {
        $this->ensureState(RoomBookingState::Requested);
        $this->update(['state' => RoomBookingState::Approved, 'approved_by' => auth()->id()]);
    }

    public function reject(?string $note = null): void
    {
        $this->ensureState(RoomBookingState::Requested);
        $this->update(['state' => RoomBookingState::Rejected, 'approved_by' => auth()->id(), 'note' => $note]);
    }

    public function cancel(): void
    {
        $this->ensureState(RoomBookingState::Requested, RoomBookingState::Approved);
        $this->update(['state' => RoomBookingState::Cancelled]);
    }

    protected function ensureState(RoomBookingState ...$states): void
    {
        if (! in_array($this->state, $states, true)) {
            throw new LogicException("Pemesanan berstatus {$this->state->getLabel()} tidak bisa diproses.");
        }
    }

    public function auditLabel(): string
    {
        return trim($this->number.' '.$this->title);
    }
}
