<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status permintaan barang. */
enum SupplyRequestState: string implements HasColor, HasLabel
{
    use HasOptions;

    case Submitted = 'submitted';
    case Approved = 'approved';
    case Issued = 'issued';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Issued => 'Diserahkan',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Approved => 'info',
            self::Issued => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
