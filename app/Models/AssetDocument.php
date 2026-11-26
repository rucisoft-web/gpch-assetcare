<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use App\Support\Fmt;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Kartu dokumen register (bagian 3.1): legalitas, izin, sertifikat K3, kontrak, polis, dokumen perolehan.
 * Status dihitung dari tanggal & tindakan (perpanjangan, diganti, dicabut), bukan diketik.
 */
class AssetDocument extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id'];

    protected $attributes = ['version' => 1, 'status' => 'valid', 'classification' => 'internal'];

    public const EXPIRING_DAYS = 90;

    public const STATUSES = [
        'valid' => 'Berlaku', 'expiring' => 'Akan berakhir', 'expired' => 'Kedaluwarsa',
        'renewing' => 'Dalam perpanjangan', 'replaced' => 'Diganti', 'revoked' => 'Dicabut',
    ];

    public const STATUS_COLORS = ['valid' => 'success', 'expiring' => 'warning', 'expired' => 'danger', 'renewing' => 'info', 'replaced' => 'gray', 'revoked' => 'gray'];

    public const CLASSIFICATIONS = ['umum' => 'Umum', 'internal' => 'Internal', 'terbatas' => 'Terbatas', 'rahasia' => 'Rahasia'];

    public const ORIGINAL_LOCATIONS = [
        'brankas' => 'Brankas legal', 'notaris' => 'Notaris', 'bank' => 'Dijaminkan ke bank', 'unit' => 'Disimpan unit / departemen',
        'salinan' => 'Hanya salinan (asli tidak ada)', 'lainnya' => 'Lainnya',
    ];

    /** Status yang masih "hidup" (dipantau pengingat & kelengkapan). */
    public const LIVE = ['valid', 'expiring', 'expired', 'renewing'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'valid_until' => 'date',
            'no_expiry' => 'boolean',
            'renewal_submitted_at' => 'date',
            'revoked_at' => 'datetime',
            'contract_value' => 'decimal:2',
            'premium' => 'decimal:2',
            'sum_insured' => 'decimal:2',
            'renewal_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AssetDocument $d) {
            $d->created_by ??= Auth::id();
            if (! $d->register_no) {
                $type = DocumentType::find($d->document_type_id);
                $cat = $type?->archive_category ?? 'DOK';
                $year = (int) now(Fmt::tz())->year;
                $d->register_no = sprintf('%s/%d/%04d', $cat, $year, Sequence::increment($d->property_id, 'DOC-'.$cat, $year));
            }
        });
        static::saving(function (AssetDocument $d) {
            if ($d->no_expiry) {
                $d->valid_until = null;
            }
            $d->status = $d->computeStatus();
            if ($d->exists && $d->isDirty()) {
                $d->version = ((int) $d->getOriginal('version')) + 1;
            }
        });
    }

    // ---- relasi
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_document_links')->withTimestamps();
    }

    public function files(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderByDesc('file_version')->orderByDesc('id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function backupPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'backup_pic_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    // ---- aturan
    public function computeStatus(?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::now(Fmt::tz())->startOfDay();
        if ($this->revoked_at) {
            return 'revoked';
        }
        if ($this->replaced_by_id) {
            return 'replaced';
        }
        if ($this->renewal_submitted_at) {
            return 'renewing';
        }
        if ($this->no_expiry || ! $this->valid_until) {
            return 'valid';
        }
        $days = $this->daysLeft($today);
        if ($days < 0) {
            return 'expired';
        }

        return $days <= max(self::EXPIRING_DAYS, $this->noticeLead()) ? 'expiring' : 'valid';
    }

    /** Sisa hari sampai tanggal berakhir (negatif = lewat). */
    public function daysLeft(?CarbonImmutable $today = null): ?int
    {
        if (! $this->valid_until) {
            return null;
        }
        $today ??= CarbonImmutable::now(Fmt::tz())->startOfDay();

        return (int) $today->diffInDays(CarbonImmutable::parse($this->valid_until->toDateString(), Fmt::tz()), false);
    }

    /** Kontrak dengan batas pemberitahuan: pengingat pertama = batas pemberitahuan + 30 hari (bagian 4.2). */
    public function noticeLead(): int
    {
        return $this->notice_days ? $this->notice_days + 30 : 0;
    }

    /** Titik pengingat (hari sebelum berakhir), terbesar dulu: H-pertama, 90, 60, 30, 7, 0. */
    public function reminderOffsets(): array
    {
        $first = max((int) ($this->type?->first_reminder_days ?? 90), $this->noticeLead());

        return collect([$first, 90, 60, 30, 7, 0])->filter(fn ($d) => $d <= $first)->unique()->sortDesc()->values()->all();
    }

    public function scopeVisibleTo(Builder $q, ?User $user): Builder
    {
        if ($user && $user->hasPermission('document.view_restricted')) {
            return $q;
        }

        return $q->whereIn('asset_documents.classification', ['umum', 'internal']);
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereIn('asset_documents.status', self::LIVE);
    }

    public function canBeSeenBy(?User $user): bool
    {
        return $user && $user->hasPermission('document.view')
            && (in_array($this->classification, ['umum', 'internal'], true) || $user->hasPermission('document.view_restricted'));
    }

    public function validityText(): string
    {
        if ($this->no_expiry) {
            return 'Tanpa batas';
        }
        if (! $this->valid_until) {
            return 'Belum diisi';
        }
        $d = $this->daysLeft();

        return $this->valid_until->format('d M Y').' ('.($d < 0 ? 'lewat '.abs($d).' hari' : ($d === 0 ? 'hari ini' : 'H-'.$d)).')';
    }

    public function auditLabel(): string
    {
        return $this->register_no.' '.$this->title;
    }

    public function getLabelAttribute(): string
    {
        return $this->register_no.' · '.$this->title;
    }
}
