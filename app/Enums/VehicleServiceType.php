<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis servis kendaraan. */
enum VehicleServiceType: string implements HasColor, HasLabel
{
    use HasOptions;

    case Routine = 'routine';
    case Repair = 'repair';
    case Tires = 'tires';
    case Body = 'body';

    public function getLabel(): string
    {
        return match ($this) {
            self::Routine => 'Servis berkala',
            self::Repair => 'Perbaikan',
            self::Tires => 'Ban & aki',
            self::Body => 'Bodi & cat',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Routine => 'info',
            self::Repair => 'warning',
            self::Tires => 'gray',
            self::Body => 'gray',
        };
    }
}
