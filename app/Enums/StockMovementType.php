<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis mutasi stok. */
enum StockMovementType: string implements HasColor, HasLabel
{
    use HasOptions;

    case In = 'in';
    case Out = 'out';
    case Adjust = 'adjust';

    public function getLabel(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
            self::Adjust => 'Penyesuaian',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'warning',
            self::Adjust => 'gray',
        };
    }
}
