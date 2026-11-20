<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Jenis dokumen register (bagian 2 Register Dokumen Aset): menentukan retensi, klasifikasi & pengingat. */
class DocumentType extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public const ARCHIVE_CATEGORIES = ['LGL' => 'LGL · Legalitas', 'LGL-IZN' => 'LGL-IZN · Izin & sertifikat', 'LGL-KTR' => 'LGL-KTR · Kontrak & polis', 'AST' => 'AST · Perolehan aset', 'PML' => 'PML · Pemeliharaan'];

    public const SECTIONS = [
        'Tanah & bangunan', 'Badan usaha & izin hotel', 'K3 & kelaikan alat', 'Lingkungan', 'Perolehan aset',
        'Kontrak pemeliharaan & vendor', 'Perangkat lunak & TI', 'Asuransi', 'Kontrak lain', 'Pemeliharaan',
    ];

    protected function casts(): array
    {
        return [
            'has_expiry' => 'boolean',
            'is_k3' => 'boolean',
            'is_insurance' => 'boolean',
            'is_contract' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function options(): array
    {
        return static::where('is_active', true)->orderBy('sort')->get()
            ->groupBy('section')->map(fn ($g) => $g->mapWithKeys(fn ($t) => [$t->id => $t->code.' · '.$t->name])->all())->all();
    }

    public function requirements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' · '.$this->name;
    }
}
