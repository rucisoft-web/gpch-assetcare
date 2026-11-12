<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Peran pengguna dalam satu perusahaan. */
enum Role: string implements HasColor, HasLabel
{
    use HasOptions;

    case Manajer = 'manajer';
    case Staf = 'staf';
    case Eksekutif = 'eksekutif';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manajer => 'Manajer',
            self::Staf => 'Staf',
            self::Eksekutif => 'Eksekutif (hanya baca)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manajer => 'primary',
            self::Staf => 'info',
            self::Eksekutif => 'gray',
        };
    }

    /** Kelola data induk, konfigurasi, CAPEX, persetujuan bagian umum, dan penghapusan. */
    public function canManage(): bool
    {
        return $this === self::Manajer;
    }

    /** Catat dan ubah data operasional (aset, pemeliharaan, risiko, pemesanan, persediaan, dokumen). */
    public function canOperate(): bool
    {
        return $this !== self::Eksekutif;
    }
}
