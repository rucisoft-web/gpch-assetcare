<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/** Template aset per tipe kamar. Versi published terkunci; revisi membuat versi baru. */
class RoomAssetTemplate extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Diarsipkan'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $t) {
            if ($t->getOriginal('status') === 'published' && $t->isDirty(['code', 'version', 'room_type_id', 'name'])) {
                throw ValidationException::withMessages(['status' => 'Template published terkunci. Buat versi baru untuk revisi.']);
            }
        });
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RoomTemplateItem::class, 'template_id')->orderBy('sort')->orderBy('id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isLocked(): bool
    {
        return $this->status !== 'draft';
    }

    public function unitCount(): int
    {
        return (int) $this->items->where('mode', 'individual')->sum('quantity');
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' v'.$this->version.' · '.$this->name;
    }

    /** Salin menjadi versi draft berikutnya (item_key dipertahankan). */
    public function newVersion(): self
    {
        $next = (int) static::where('code', $this->code)->max('version') + 1;
        $copy = $this->replicate(['status', 'published_at', 'reviewed_by']);
        $copy->fill(['version' => $next, 'status' => 'draft', 'prepared_by' => auth()->id()])->save();
        foreach ($this->items as $item) {
            $copy->items()->create($item->replicate(['template_id'])->toArray());
        }

        return $copy;
    }
}
