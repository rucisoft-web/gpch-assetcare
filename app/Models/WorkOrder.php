<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkOrder extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id', 'number'];

    protected $attributes = ['version' => 1];

    public const STATUSES = [
        'draft' => 'Draft', 'awaiting_approval' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'assigned' => 'Ditugaskan',
        'in_progress' => 'Dikerjakan', 'on_hold' => 'Ditunda (hold)', 'completed' => 'Selesai teknisi', 'verified' => 'Terverifikasi',
        'closed' => 'Ditutup', 'cancelled' => 'Dibatalkan', 'reopened' => 'Dibuka ulang',
    ];

    public const STATUS_COLORS = [
        'draft' => 'gray', 'awaiting_approval' => 'warning', 'approved' => 'info', 'assigned' => 'info', 'in_progress' => 'primary',
        'on_hold' => 'warning', 'completed' => 'success', 'verified' => 'success', 'closed' => 'gray', 'cancelled' => 'gray', 'reopened' => 'danger',
    ];

    public const OPEN = ['draft', 'awaiting_approval', 'approved', 'assigned', 'in_progress', 'on_hold', 'completed', 'verified', 'reopened'];

    public const TYPES = ['corrective' => 'Perbaikan (korektif)', 'emergency' => 'Darurat', 'preventive' => 'Preventive maintenance', 'inspection' => 'Inspeksi', 'predictive' => 'Predictive (berbasis kondisi)', 'improvement' => 'Penyempurnaan'];

    public const SOURCES = ['manual' => 'Dibuat langsung', 'request' => 'Laporan kerusakan', 'pm' => 'Rencana PM', 'predictive' => 'Pemantauan kondisi'];

    public const PERMITS = ['ptw' => 'Permit to work', 'loto' => 'Isolasi energi / LOTO', 'hot_work' => 'Hot work', 'height' => 'Kerja di ketinggian', 'room_access' => 'Serah terima akses kamar (FO)'];

    public const TEST_RESULTS = ['pass' => 'Lulus uji fungsi', 'partial' => 'Berfungsi sebagian / solusi sementara', 'fail' => 'Gagal uji fungsi'];

    protected function casts(): array
    {
        return [
            'is_safety' => 'boolean', 'is_emergency' => 'boolean', 'requires_approval' => 'boolean', 'needs_vendor' => 'boolean',
            'room_access_needed' => 'boolean', 'fo_confirmed' => 'boolean',
            'team_ids' => 'array', 'permits' => 'array', 'checklist' => 'array', 'pm_checklist' => 'array', 'planned_due_at' => 'date',
            'estimated_cost' => 'decimal:2', 'estimated_hours' => 'decimal:2',
            'received_at' => 'datetime', 'response_due_at' => 'datetime', 'due_at' => 'datetime', 'hold_started_at' => 'datetime',
            'approved_at' => 'datetime', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'restored_at' => 'datetime',
            'completed_at' => 'datetime', 'verified_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $w) {
            $w->number ??= Sequence::next('WO', $w->property_id);
            $w->created_by ??= auth()->id();
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function requests(): BelongsToMany
    {
        return $this->belongsToMany(ServiceRequest::class, 'request_links')->withTimestamps();
    }

    public function statusEvents(): MorphMany
    {
        return $this->morphMany(StatusEvent::class, 'subject')->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(WoTimeLog::class);
    }

    public function costLines(): HasMany
    {
        return $this->hasMany(WoCostLine::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function roomBlocks(): HasMany
    {
        return $this->hasMany(RoomBlock::class);
    }

    /** Teknisi melihat tugasnya; pemegang wo.assign / wo.verify / laporan lintas dept melihat semua. */
    public function scopeVisibleTo(Builder $q, ?User $user): Builder
    {
        if (! $user || $user->hasAnyPermission(['wo.assign', 'wo.verify', 'wo.approve', 'request.view_all', 'wo.cost_finalize'])) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('pic_id', $user->id)->orWhereJsonContains('team_ids', (string) $user->id)
            ->orWhereJsonContains('team_ids', $user->id)->orWhere('created_by', $user->id)
            ->orWhereHas('requests', fn ($r) => $r->where('reporter_id', $user->id)));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isCritical(): bool
    {
        return $this->is_safety || in_array($this->priority, ['P1', 'P2'], true) || (bool) $this->asset?->isCritical();
    }

    public function totalCost(?string $status = null): float
    {
        return (float) $this->costLines->when($status, fn ($c) => $c->where('cost_status', $status))->sum('amount');
    }

    public function photoCount(): int
    {
        return $this->attachments->whereIn('kind', ['before', 'after', 'evidence', 'photo'])->count();
    }

    public function pmSchedule(): BelongsTo
    {
        return $this->belongsTo(PmSchedule::class);
    }

    public function conditionReading(): BelongsTo
    {
        return $this->belongsTo(ConditionReading::class);
    }
}
