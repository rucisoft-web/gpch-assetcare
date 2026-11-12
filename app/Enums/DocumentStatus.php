<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status masa berlaku dokumen, dihitung dari tanggal kedaluwarsa. */
enum DocumentStatus: string implements HasColor, HasLabel
{
    use HasOptions;

    case Valid = 'valid';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Permanent = 'permanent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Valid => 'Berlaku',
            self::Expiring => 'Segera berakhir',
            self::Expired => 'Kedaluwarsa',
            self::Permanent => 'Tanpa batas',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::Expiring => 'warning',
            self::Expired => 'danger',
            self::Permanent => 'gray',
        };
    }
}
