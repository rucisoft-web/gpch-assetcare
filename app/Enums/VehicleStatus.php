<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status ketersediaan kendaraan. */
enum VehicleStatus: string implements HasColor, HasLabel
{
    use HasOptions;

    case Available = 'available';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Maintenance => 'Di bengkel',
            self::Inactive => 'Tidak aktif',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Maintenance => 'warning',
            self::Inactive => 'gray',
        };
    }
}
