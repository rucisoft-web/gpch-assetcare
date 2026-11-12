<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis kendaraan dinas. */
enum VehicleType: string implements HasColor, HasLabel
{
    use HasOptions;

    case Car = 'car';
    case Minibus = 'minibus';
    case Pickup = 'pickup';
    case Motorcycle = 'motorcycle';

    public function getLabel(): string
    {
        return match ($this) {
            self::Car => 'Mobil penumpang',
            self::Minibus => 'Minibus / van',
            self::Pickup => 'Pikap / truk ringan',
            self::Motorcycle => 'Sepeda motor',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Car => 'gray',
            self::Minibus => 'gray',
            self::Pickup => 'gray',
            self::Motorcycle => 'gray',
        };
    }
}
