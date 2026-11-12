<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jenis dokumen legal dan perizinan. */
enum DocumentType: string implements HasColor, HasLabel
{
    use HasOptions;

    case License = 'license';
    case Certificate = 'certificate';
    case Contract = 'contract';
    case Insurance = 'insurance';
    case Tax = 'tax';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::License => 'Izin usaha & operasional',
            self::Certificate => 'Sertifikat & laik fungsi',
            self::Contract => 'Kontrak & perjanjian',
            self::Insurance => 'Polis asuransi',
            self::Tax => 'Pajak & retribusi',
            self::Other => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::License => 'gray',
            self::Certificate => 'gray',
            self::Contract => 'gray',
            self::Insurance => 'gray',
            self::Tax => 'gray',
            self::Other => 'gray',
        };
    }
}
