<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Kategori barang persediaan bagian umum. */
enum SupplyCategory: string implements HasColor, HasLabel
{
    use HasOptions;

    case Stationery = 'stationery';
    case Printing = 'printing';
    case Cleaning = 'cleaning';
    case Pantry = 'pantry';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Stationery => 'ATK',
            self::Printing => 'Tinta & cetakan',
            self::Cleaning => 'Kebersihan',
            self::Pantry => 'Pantry & konsumsi',
            self::Other => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stationery => 'gray',
            self::Printing => 'gray',
            self::Cleaning => 'gray',
            self::Pantry => 'gray',
            self::Other => 'gray',
        };
    }
}
