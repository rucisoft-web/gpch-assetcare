<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Laporan kerusakan. Aset boleh belum diketahui; Engineering melengkapi saat triase. */
class ServiceRequest extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id', 'number'];

    protected $attributes = ['version' => 1];

    public const STATUSES = [
        'submitted' => 'Diterima', 'triaged' => 'Ditriase', 'linked_to_work_order' => 'Dalam work order',
        'resolved' => 'Selesai ditangani', 'closed' => 'Ditutup', 'rejected' => 'Ditolak', 'duplicate' => 'Duplikat',
    ];

    public const STATUS_COLORS = ['submitted' => 'danger', 'triaged' => 'warning', 'linked_to_work_order' => 'info', 'resolved' => 'success', 'closed' => 'gray', 'rejected' => 'gray', 'duplicate' => 'gray'];

    public const OPEN = ['submitted', 'triaged', 'linked_to_work_order'];

    public const CATEGORIES = [
        'listrik' => 'Listrik', 'air' => 'Air / plumbing / kebocoran', 'ac' => 'AC / HVAC', 'elektronik' => 'Elektronik (TV, telepon)',
        'furniture' => 'Furniture / bed', 'bangunan' => 'Bangunan / cat / plafon', 'kitchen' => 'Peralatan kitchen', 'mice' => 'Peralatan MICE',
        'keselamatan' => 'Keselamatan (APAR, alarm, detektor)', 'it' => 'IT & keamanan', 'lift' => 'Lift', 'lainnya' => 'Lainnya',
    ];

    public const IMPACTS = [
        'keselamatan' => 'Bahaya keselamatan', 'kamar_tidak_dijual' => 'Kamar tidak dapat dijual', 'acara_terganggu' => 'Acara aktif terganggu',
        'layanan_alternatif' => 'Masih ada layanan alternatif', 'kosmetik' => 'Kosmetik / penyempurnaan',
    ];

    public const PRIORITIES = ['P1' => 'P1 Darurat', 'P2' => 'P2 Mendesak', 'P3' => 'P3 Normal', 'P4' => 'P4 Terencana'];

    public const PRIORITY_COLORS = ['P1' => 'danger', 'P2' => 'warning', 'P3' => 'info', 'P4' => 'gray'];

    protected function casts(): array
    {
        return [
            'is_safety' => 'boolean', 'room_block_proposed' => 'boolean',
            'received_at' => 'datetime', 'response_due_at' => 'datetime', 'response_at' => 'datetime',
            'triaged_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->number ??= Sequence::next('LK', $r->property_id));
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function quantityGroup(): BelongsTo
    {
        return $this->belongsTo(AssetQuantityGroup::class, 'quantity_group_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reporterDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'reporter_department_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'duplicate_of_id');
    }

    public function workOrders(): BelongsToMany
    {
        return $this->belongsToMany(WorkOrder::class, 'request_links')->withTimestamps();
    }

    public function statusEvents(): MorphMany
    {
        return $this->morphMany(StatusEvent::class, 'subject')->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Pelapor biasa hanya melihat laporannya & laporan departemennya. */
    public function scopeVisibleTo(Builder $q, ?User $user): Builder
    {
        if (! $user || $user->hasAnyPermission(['request.view_all', 'request.triage'])) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('reporter_id', $user->id)
            ->when($user->department_id && $user->hasRole('hod'), fn ($x) => $x->orWhere('reporter_department_id', $user->department_id)));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function responseOverdue(): bool
    {
        return $this->response_due_at && ($this->response_at ?? now())->greaterThan($this->response_due_at);
    }
}
