<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Asset extends Model
{
    use Auditable, BelongsToProperty, HasUlids;

    protected $guarded = ['id', 'asset_code', 'public_id'];

    /** Kondisi fisik A–E berdiri sendiri dari status lain. */
    public const CONDITIONS = ['A' => 'A — baik', 'B' => 'B — kerusakan ringan', 'C' => 'C — perlu perbaikan', 'D' => 'D — perlu kajian penggantian', 'E' => 'E — tidak layak'];

    public const CRITICALITY = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'critical' => 'Kritis'];

    public const OPERATIONAL = ['serviceable' => 'Layak pakai', 'degraded' => 'Menurun', 'under_repair' => 'Dalam perbaikan', 'out_of_service' => 'Tidak beroperasi'];

    public const LIFECYCLE = ['draft' => 'Draft', 'active' => 'Aktif', 'retired' => 'Berhenti dipakai', 'disposed' => 'Dihapus (disposed)', 'lost' => 'Hilang'];

    public const VERIFICATION = ['unverified' => 'Belum terverifikasi', 'verified' => 'Terverifikasi', 'discrepancy' => 'Selisih / tidak ditemukan'];

    public const OWNERSHIP = ['milik' => 'Milik hotel', 'sewa' => 'Sewa', 'vendor' => 'Milik vendor', 'konsinyasi' => 'Konsinyasi'];

    /** Dimensi status yang riwayatnya dicatat terpisah. */
    public const TRACKED = ['condition_grade', 'operational_status', 'lifecycle_status', 'location_id', 'department_id', 'verification_status', 'criticality', 'custodian_id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'in_service_date' => 'date',
            'warranty_until' => 'date',
            'last_inspected_at' => 'datetime',
            'verified_at' => 'datetime',
            'in_transit' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Asset $a) {
            $a->asset_code ??= \App\Support\AssetCode::next($a) ?? Sequence::assetCode($a->property_id);
            $a->created_by ??= Auth::id();
            $cat = $a->category_id ? AssetCategory::withoutGlobalScopes()->find($a->category_id) : null;
            $a->criticality ??= $cat?->default_criticality;
        });
        // pindah lokasi / ganti jenis → kode ikut berubah; kode lama tetap tercatat & bisa dicari
        static::updating(function (Asset $a) {
            if ($a->isDirty('location_id') || $a->isDirty('category_id')) {
                $prefix = \App\Support\AssetCode::prefix($a->location_id, $a->category_id);
                if ($prefix && ! str_starts_with((string) $a->asset_code, $prefix.'-')) {
                    $old = $a->getOriginal('asset_code');
                    $a->asset_code = \App\Support\AssetCode::next($a);
                    $a->former_codes = \App\Support\AssetCode::appendFormer($a->former_codes, $old);
                }
            }
        });
        static::updated(function (Asset $a) {
            if ($a->wasChanged('asset_code') && ($label = $a->activeLabel) && ($last = $label->printItems()->latest('id')->first())
                && $last->code_snapshot !== $a->asset_code && $label->currentInstallationStatus() !== 'needs_relabel') {
                InstallationEvent::create([
                    'asset_label_id' => $label->id, 'asset_id' => $a->id, 'location_id' => $a->location_id, 'print_item_id' => $last->id,
                    'status' => 'needs_relabel', 'note' => 'Kode aset berubah: label tertulis "'.$last->code_snapshot.'", sekarang "'.$a->asset_code.'"',
                    'actor_id' => Auth::id(), 'occurred_at' => now(),
                ]);
                app(\App\Services\LabelService::class)->syncInstallStatus($label);
            }
        });
        static::saving(function (Asset $a) {
            $a->guardHierarchy();
            if ($a->isDirty('lifecycle_status') && $a->lifecycle_status === 'active') {
                $missing = $a->missingForActivation();
                if ($missing) {
                    throw ValidationException::withMessages(['lifecycle_status' => 'Belum dapat diaktifkan, lengkapi: '.implode(', ', $missing).'.']);
                }
            }
            if ($a->exists && $a->isDirty()) {
                $a->version = ((int) $a->getOriginal('version')) + 1;
            }
        });
        static::created(fn (Asset $a) => $a->recordStatusEvents(true));
        static::updated(fn (Asset $a) => $a->recordStatusEvents(false));
        static::deleting(function (Asset $a) {
            if ($a->lifecycle_status !== 'draft' || $a->hasTransactions()) {
                throw ValidationException::withMessages(['asset' => 'Aset aktif atau yang sudah bertransaksi tidak dapat dihapus permanen. Gunakan status siklus hidup.']);
            }
        });
    }

    // ---- relasi
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Asset::class, 'parent_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function financial(): HasOne
    {
        return $this->hasOne(AssetFinancial::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(AssetStatusEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(AssetLabel::class);
    }

    public function activeLabel(): HasOne
    {
        return $this->hasOne(AssetLabel::class)->where('status', 'active');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** URL foto utama (photo_path, atau foto terbaru di tab Foto & dokumen); null bila belum ada. */
    public function mainPhotoUrl(): ?string
    {
        if (! $this->photo_path && ! $this->attachments()->where('kind', 'photo')->exists()) {
            return null;
        }

        return route('assets.photo', ['asset' => $this->getKey(), 'v' => substr(md5((string) $this->photo_path.$this->updated_at), 0, 8)]);
    }

    /** Dokumen register yang menaungi aset ini (boleh nol). */
    public function documents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AssetDocument::class, 'asset_document_links')->withTimestamps();
    }

    /** Kelas kebijakan penyusutan: pengecualian di data keuangan, selain itu dari kategori. */
    public function depreciationClass(): ?DepreciationClass
    {
        $id = $this->financial?->depreciation_class_id ?? $this->category?->depreciation_class_id;

        return $id ? DepreciationClass::withoutGlobalScopes()->find($id) : null;
    }

    /** Izin/riksa uji K3 kedaluwarsa → aset tidak boleh dioperasikan (Register Dokumen 4.4). */
    public function expiredK3Documents(): \Illuminate\Support\Collection
    {
        // dokumen K3 hidup yang lewat masa berlaku (termasuk yang sedang diperpanjang), kecuali sudah ada dokumen K3 jenis sama yang masih berlaku
        $today = now(\App\Support\Fmt::tz())->toDateString();
        $docs = $this->documents()->whereHas('type', fn ($t) => $t->where('is_k3', true))
            ->whereIn('asset_documents.status', AssetDocument::LIVE)->with('type')->get();

        return $docs->groupBy('document_type_id')
            ->filter(fn ($g) => $g->every(fn ($d) => ! $d->no_expiry && $d->valid_until && $d->valid_until->toDateString() < $today))
            ->flatten(1)->values();
    }

    public function operationBlocked(): bool
    {
        return $this->expiredK3Documents()->isNotEmpty();
    }

    public function slotAssignment(): HasOne
    {
        return $this->hasOne(SlotAssignment::class)->whereNotNull('active_asset_key');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ---- aturan
    public function scopeVisibleTo(Builder $q, ?User $user): Builder
    {
        return $q; // register dapat dilihat pemegang asset.view; field finansial dibatasi terpisah
    }

    /** Field wajib sebelum aktivasi (spesifikasi bagian 4). */
    public function missingForActivation(): array
    {
        $labels = [
            'name' => 'nama', 'category_id' => 'kategori', 'location_id' => 'lokasi', 'department_id' => 'departemen',
            'ownership' => 'kepemilikan', 'condition_grade' => 'kondisi awal', 'criticality' => 'kritikalitas',
        ];
        $missing = [];
        foreach ($labels as $field => $label) {
            if (blank($this->{$field})) {
                $missing[] = $label;
            }
        }
        if (blank($this->custodian_id) && blank($this->custodian_unit)) {
            $missing[] = 'penanggung jawab / unit penanggung jawab';
        }
        if ($this->verification_status !== 'verified') {
            $missing[] = 'verifikasi fisik';
        }

        return $missing;
    }

    public function hasTransactions(): bool
    {
        return $this->workOrders()->exists() || $this->serviceRequests()->exists()
            || LabelPrintItem::where('asset_id', $this->id)->exists()
            || AssetTransferItem::where('asset_id', $this->id)->exists();
    }

    /** Cegah hubungan induk berulang / siklus. */
    protected function guardHierarchy(): void
    {
        if (! $this->parent_id) {
            return;
        }
        if ((int) $this->parent_id === (int) $this->id) {
            throw ValidationException::withMessages(['parent_id' => 'Aset tidak dapat menjadi induk dirinya sendiri.']);
        }
        $seen = [$this->id];
        $p = Asset::withoutGlobalScopes()->find($this->parent_id);
        while ($p) {
            if (in_array($p->id, $seen, true)) {
                throw ValidationException::withMessages(['parent_id' => 'Hubungan induk–komponen membentuk siklus.']);
            }
            if ((int) $p->property_id !== (int) $this->property_id) {
                throw ValidationException::withMessages(['parent_id' => 'Aset induk harus pada properti yang sama.']);
            }
            $seen[] = $p->id;
            $p = $p->parent_id ? Asset::withoutGlobalScopes()->find($p->parent_id) : null;
        }
    }

    protected function recordStatusEvents(bool $created): void
    {
        $changes = $created ? array_filter($this->only(self::TRACKED), fn ($v) => $v !== null) : array_intersect_key($this->getChanges(), array_flip(self::TRACKED));
        foreach ($changes as $dim => $new) {
            $old = $created ? null : $this->getOriginal($dim);
            AssetStatusEvent::create([
                'asset_id' => $this->id,
                'dimension' => $dim,
                'old_value' => $old,
                'new_value' => $new,
                'old_label' => self::dimensionLabel($dim, $old),
                'new_label' => self::dimensionLabel($dim, $new),
                'reason' => $this->auditReason ?? ($created ? 'Registrasi aset' : null),
                'source_type' => $this->statusSource[0] ?? null,
                'source_id' => $this->statusSource[1] ?? null,
                'actor_id' => Auth::id(),
                'occurred_at' => now(),
            ]);
        }
        $this->statusSource = [];
    }

    /** [tipe, id] sumber perubahan status (mis. ['work_order', 12]). */
    public array $statusSource = [];

    public static function dimensionLabel(string $dim, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($dim) {
            'condition_grade' => self::CONDITIONS[$value] ?? $value,
            'operational_status' => self::OPERATIONAL[$value] ?? $value,
            'lifecycle_status' => self::LIFECYCLE[$value] ?? $value,
            'verification_status' => self::VERIFICATION[$value] ?? $value,
            'criticality' => self::CRITICALITY[$value] ?? $value,
            'location_id' => Location::withoutGlobalScopes()->find($value)?->full_path,
            'department_id' => Department::withoutGlobalScopes()->find($value)?->name,
            'custodian_id' => User::find($value)?->name,
            default => (string) $value,
        };
    }

    public const DIMENSIONS = [
        'condition_grade' => 'Kondisi', 'operational_status' => 'Status operasional', 'lifecycle_status' => 'Siklus hidup',
        'location_id' => 'Lokasi', 'department_id' => 'Departemen', 'verification_status' => 'Verifikasi',
        'criticality' => 'Kritikalitas', 'custodian_id' => 'Penanggung jawab',
    ];

    /** Peringatan duplikasi nomor seri (bukan penolakan). */
    public function duplicateSerials()
    {
        if (blank($this->serial_number)) {
            return collect();
        }

        return static::where('serial_number', $this->serial_number)->whereKeyNot($this->id)->get(['id', 'asset_code', 'name']);
    }

    public function warrantyActive(): ?bool
    {
        return $this->warranty_until ? $this->warranty_until->endOfDay()->isFuture() : null;
    }

    public function getLabelAttribute(): string
    {
        return $this->asset_code.' · '.$this->name;
    }

    public function isCritical(): bool
    {
        return in_array($this->criticality, ['high', 'critical'], true);
    }

    public function conditionReadings(): HasMany
    {
        return $this->hasMany(ConditionReading::class);
    }

    public function pmSchedules(): HasMany
    {
        return $this->hasMany(PmSchedule::class);
    }
}
