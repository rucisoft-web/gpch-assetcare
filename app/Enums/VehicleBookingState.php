<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status pemesanan kendaraan dinas. */
enum VehicleBookingState: string implements HasColor, HasLabel
{
    use HasOptions;

    case Requested = 'requested';
    case Approved = 'approved';
    case InUse = 'in_use';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Diajukan',
            self::Approved => 'Disetujui',
            self::InUse => 'Sedang dipakai',
            self::Returned => 'Selesai',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'info',
            self::InUse => 'primary',
            self::Returned => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
