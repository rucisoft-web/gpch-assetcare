<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dokumen legal, perizinan, kontrak, dan polis dengan pemantauan masa berlaku. */
class LegalDocument extends Model
{
    use Auditable, BelongsToCompany;

    protected $attributes = ['document_type' => 'license', 'reminder_days' => 60, 'is_active' => true];

    protected $guarded = ['id'];

    protected array $companyParents = ['vehicle_id' => Vehicle::class, 'asset_id' => Asset::class];

    protected array $tracked = ['expiry_date', 'number', 'is_active'];

    protected function casts(): array
    {
        return ['document_type' => DocumentType::class, 'issued_date' => 'date', 'expiry_date' => 'date', 'is_active' => 'boolean'];
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function getStatusAttribute(): DocumentStatus
    {
        return match (true) {
            $this->expiry_date === null => DocumentStatus::Permanent,
            $this->expiry_date->lt(today()) => DocumentStatus::Expired,
            $this->expiry_date->lte(today()->addDays($this->reminder_days)) => DocumentStatus::Expiring,
            default => DocumentStatus::Valid,
        };
    }

    public function daysLeft(): ?int
    {
        return $this->expiry_date ? (int) today()->diffInDays($this->expiry_date, false) : null;
    }

    public function scopeWithStatus(Builder $query, DocumentStatus $status): Builder
    {
        $today = today()->toDateString();

        return match ($status) {
            DocumentStatus::Permanent => $query->whereNull('expiry_date'),
            DocumentStatus::Expired => $query->whereDate('expiry_date', '<', $today),
            // Batas pengingat berbeda per dokumen, jadi dihitung di PHP (sama di MySQL dan SQLite).
            DocumentStatus::Expiring => $query->whereDate('expiry_date', '>=', $today)
                ->whereIn('id', static::expiringIds()),
            DocumentStatus::Valid => $query->whereDate('expiry_date', '>=', $today)
                ->whereNotIn('id', static::expiringIds()),
        };
    }

    /** Dokumen aktif yang perlu ditindaklanjuti: kedaluwarsa atau masuk masa pengingat. */
    public function scopeNeedsAction(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(fn (Builder $q) => $q
            ->whereDate('expiry_date', '<', today())
            ->orWhereIn('id', static::expiringIds()));
    }

    /** @return array<int, int> */
    protected static function expiringIds(): array
    {
        return static::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->get(['id', 'expiry_date', 'reminder_days'])
            ->filter(fn (self $doc) => $doc->expiry_date->lte(today()->addDays($doc->reminder_days)))
            ->pluck('id')
            ->all();
    }
}
