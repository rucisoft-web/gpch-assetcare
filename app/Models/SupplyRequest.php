<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Enums\SupplyRequestState;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Permintaan barang (ATK dan persediaan) dari karyawan atau departemen. */
class SupplyRequest extends Model
{
    use Auditable, BelongsToCompany;

    public const OPEN_STATES = ['submitted', 'approved'];

    protected $attributes = ['state' => 'submitted'];

    protected $guarded = ['id'];

    protected array $companyParents = ['department_id' => Department::class];

    protected array $tracked = ['state'];

    protected function casts(): array
    {
        return ['state' => SupplyRequestState::class, 'requested_at' => 'datetime', 'needed_by' => 'date', 'issued_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (SupplyRequest $request) {
            $request->number ??= Sequence::next('supply_request', $request->company_id);
            $request->requested_at ??= now();
            $request->requester_id ??= auth()->id();
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplyRequestLine::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('state', self::OPEN_STATES);
    }

    public function approve(): void
    {
        $this->ensureState(SupplyRequestState::Submitted);
        $this->update(['state' => SupplyRequestState::Approved, 'approved_by' => auth()->id()]);
    }

    public function reject(?string $note = null): void
    {
        $this->ensureState(SupplyRequestState::Submitted, SupplyRequestState::Approved);
        $this->update(['state' => SupplyRequestState::Rejected, 'approved_by' => auth()->id(), 'note' => $note]);
    }

    public function cancel(): void
    {
        $this->ensureState(SupplyRequestState::Submitted, SupplyRequestState::Approved);
        $this->update(['state' => SupplyRequestState::Cancelled]);
    }

    /**
     * Serahkan barang: kurangi stok setiap baris sekaligus.
     *
     * @param  array<int, float>  $quantities  id baris => jumlah diserahkan (default: jumlah diminta)
     */
    public function issue(array $quantities = []): void
    {
        $this->ensureState(SupplyRequestState::Submitted, SupplyRequestState::Approved);

        DB::transaction(function () use ($quantities) {
            foreach ($this->lines()->with('item')->get() as $line) {
                $quantity = (float) ($quantities[$line->id] ?? $line->quantity);

                if ($quantity > 0) {
                    $line->item->move(StockMovementType::Out, $quantity, [
                        'supply_request_id' => $this->id,
                        'reference' => $this->number,
                    ]);
                }

                $line->update(['issued_quantity' => $quantity]);
            }

            $this->update([
                'state' => SupplyRequestState::Issued,
                'issued_at' => now(),
                'approved_by' => $this->approved_by ?? auth()->id(),
            ]);
        });
    }

    protected function ensureState(SupplyRequestState ...$states): void
    {
        if (! in_array($this->state, $states, true)) {
            throw new LogicException("Permintaan berstatus {$this->state->getLabel()} tidak bisa diproses.");
        }
    }

    public function auditLabel(): string
    {
        return (string) $this->number;
    }
}
