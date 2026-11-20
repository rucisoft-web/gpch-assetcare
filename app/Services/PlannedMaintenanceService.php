<?php

namespace App\Services;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Asset;
use App\Models\ConditionParameter;
use App\Models\ConditionReading;
use App\Models\PmPlan;
use App\Models\PmSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Fmt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pemeliharaan terencana:
 *  - preventive: rencana PM (interval hari / meter) → WO preventive otomatis H-lead sebelum jatuh tempo;
 *    jadwal maju setelah WO diverifikasi, dihitung dari tanggal selesai (bukan dari jatuh tempo).
 *  - predictive: pembacaan kondisi melewati batas → WO predictive otomatis (satu WO terbuka per aset & parameter).
 */
class PlannedMaintenanceService
{
    public function __construct(protected SlaService $sla, protected NotificationService $notify) {}

    protected function today(): CarbonImmutable
    {
        return CarbonImmutable::now(Fmt::tz())->startOfDay();
    }

    /** Buat / rapikan baris jadwal per aset untuk rencana ini. */
    public function syncSchedules(PmPlan $plan): int
    {
        if (! $plan->is_active) {
            return 0;
        }
        $n = 0;
        $start = $plan->starts_on ? CarbonImmutable::parse($plan->starts_on->toDateString()) : $this->today();
        foreach ($plan->targetAssets()->pluck('id') as $assetId) {
            $s = PmSchedule::firstOrCreate(['pm_plan_id' => $plan->id, 'asset_id' => $assetId], [
                'next_due_at' => $plan->interval_days ? $start->toDateString() : null,
            ]);
            if ($s->wasRecentlyCreated) {
                $n++;
                if ($plan->meter_parameter_id && $plan->interval_meter) {
                    $last = $this->latestMeter($assetId, $plan->meter_parameter_id);
                    $s->update(['last_meter' => $last, 'next_due_meter' => ($last ?? 0) + $plan->interval_meter]);
                }
            }
        }

        return $n;
    }

    /**
     * Buat rencana PM per kategori dari interval PM bawaan kategori (default_pm_interval_days) untuk kategori yang
     * punya aset aktif dan belum punya rencana. Titik awal cepat saat kick off; rencana dapat disunting sesudahnya.
     */
    public function plansFromCategoryDefaults(?User $by = null): int
    {
        $n = 0;
        $cats = \App\Models\AssetCategory::query()->whereNotNull('default_pm_interval_days')
            ->whereHas('assets', fn ($q) => $q->whereIn('lifecycle_status', ['draft', 'active']))->get();
        foreach ($cats as $c) {
            if (PmPlan::where('scope', 'category')->where('category_id', $c->id)->exists()) {
                continue;
            }
            $plan = PmPlan::create([
                'property_id' => $c->property_id, 'code' => \Illuminate\Support\Str::limit('PM-'.$c->code, 30, ''), 'name' => 'PM rutin '.$c->name,
                'scope' => 'category', 'category_id' => $c->id, 'wo_type' => 'preventive', 'interval_days' => $c->default_pm_interval_days,
                'starts_on' => $this->today()->addDays(7)->toDateString(), 'lead_days' => 7, 'priority' => $c->default_criticality === 'critical' ? 'P3' : 'P4',
                'is_active' => true, 'created_by' => $by?->id,
            ]);
            $this->syncSchedules($plan);
            $n++;
        }

        return $n;
    }

    public function latestMeter(int $assetId, int $parameterId): ?float
    {
        $v = ConditionReading::where('asset_id', $assetId)->where('condition_parameter_id', $parameterId)->latest('read_at')->latest('id')->value('value');

        return $v === null ? null : (float) $v;
    }

    /** Alasan jatuh tempo (null = belum). */
    public function dueReason(PmSchedule $s, ?CarbonImmutable $today = null): ?string
    {
        $today ??= $this->today();
        $plan = $s->plan;
        if ($plan->interval_days && $s->next_due_at && CarbonImmutable::parse($s->next_due_at->toDateString())->subDays($plan->lead_days)->lte($today)) {
            return 'jatuh tempo '.$s->next_due_at->format('d M Y');
        }
        if ($plan->meter_parameter_id && $plan->interval_meter && $s->next_due_meter !== null) {
            $meter = $this->latestMeter($s->asset_id, $plan->meter_parameter_id);
            if ($meter !== null && $meter >= $s->next_due_meter) {
                return 'meter '.rtrim(rtrim(number_format($meter, 3, ',', '.'), '0'), ',').' ≥ '.rtrim(rtrim(number_format($s->next_due_meter, 3, ',', '.'), '0'), ',').' '.($plan->meterParameter?->unit ?? '');
            }
        }

        return null;
    }

    /** Buat WO untuk semua jadwal yang jatuh tempo dan belum punya WO terbuka. */
    public function generateDue(?CarbonImmutable $today = null, ?int $assetId = null, ?User $by = null): int
    {
        $today ??= $this->today();
        $made = 0;
        PmPlan::withoutGlobalScopes()->where('is_active', true)->get()->each(fn ($p) => $this->syncSchedules($p));
        PmSchedule::query()->whereNull('open_work_order_id')
            ->whereHas('plan', fn ($q) => $q->withoutGlobalScopes()->where('is_active', true))
            ->whereHas('asset', fn ($q) => $q->withoutGlobalScopes()->whereIn('lifecycle_status', ['draft', 'active']))
            ->when($assetId, fn ($q) => $q->where('asset_id', $assetId))
            ->with(['plan' => fn ($q) => $q->withoutGlobalScopes()->with('meterParameter'), 'asset' => fn ($q) => $q->withoutGlobalScopes()])
            ->chunkById(200, function ($rows) use ($today, $by, &$made) {
                foreach ($rows as $s) {
                    if ($reason = $this->dueReason($s, $today)) {
                        $this->createPmWorkOrder($s, $reason, $by);
                        $made++;
                    }
                }
            });

        return $made;
    }

    public function createPmWorkOrder(PmSchedule $s, string $reason, ?User $by = null): WorkOrder
    {
        return DB::transaction(function () use ($s, $reason, $by) {
            $s = PmSchedule::whereKey($s->id)->lockForUpdate()->with(['plan' => fn ($q) => $q->withoutGlobalScopes(), 'asset' => fn ($q) => $q->withoutGlobalScopes()])->firstOrFail();
            if ($s->open_work_order_id) {
                return WorkOrder::withoutGlobalScopes()->findOrFail($s->open_work_order_id);
            }
            $plan = $s->plan;
            $asset = $s->asset;
            $now = now();
            $t = $this->sla->targets($plan->priority, $now, $asset->property_id);
            $planned = $s->next_due_at ? CarbonImmutable::parse($s->next_due_at->toDateString(), Fmt::tz())->endOfDay()->utc() : null;
            $wo = WorkOrder::create([
                'property_id' => $asset->property_id,
                'type' => $plan->wo_type,
                'source' => 'pm',
                'pm_schedule_id' => $s->id,
                'title' => $plan->name.' — '.$asset->name,
                'description' => trim(($plan->instructions ? $plan->instructions."\n\n" : '').'Rencana '.$plan->code.' ('.$plan->intervalText().'); '.$reason.'.'),
                'asset_id' => $asset->id,
                'location_id' => $asset->location_id,
                'priority' => $plan->priority,
                'status' => 'draft',
                'received_at' => $now,
                'response_due_at' => $t['response_due_at'],
                'due_at' => $planned && $planned->gt($now) ? $planned : $t['due_at'],
                'planned_due_at' => $s->next_due_at,
                'sla_calendar' => $t['calendar'],
                'pic_id' => $plan->pic_id,
                'estimated_hours' => $plan->estimated_hours,
                'needs_vendor' => $plan->needs_vendor,
                'vendor_id' => $plan->vendor_id,
                'pm_checklist' => collect($plan->checklist ?? [])->map(fn ($r) => ['item' => is_array($r) ? ($r['item'] ?? '') : (string) $r, 'done' => false])->filter(fn ($r) => filled($r['item']))->values()->all() ?: null,
                'created_by' => $by?->id,
            ]);
            app(WorkOrderService::class)->event($wo, null, 'draft', 'create', 'Dibuat otomatis dari rencana PM '.$plan->code.' ('.$reason.')');
            $s->update(['open_work_order_id' => $wo->id]);
            $to = $plan->pic ? collect([$plan->pic]) : $this->notify->usersWith('wo.assign');
            $this->notify->send($to, 'PM jatuh tempo: '.$asset->asset_code, $wo->title.' · '.$reason, WorkOrderResource::getUrl('view', ['record' => $wo], panel: 'admin'), 'pm-wo-'.$wo->id);

            return $wo;
        });
    }

    /** Dipanggil saat WO PM diverifikasi (maju) atau dibatalkan (jadwal tetap, WO dilepas). */
    public function onWorkOrderFinished(WorkOrder $wo, bool $done): void
    {
        $s = PmSchedule::with(['plan' => fn ($q) => $q->withoutGlobalScopes()])->find($wo->pm_schedule_id);
        if (! $s || (int) $s->open_work_order_id !== (int) $wo->id) {
            return;
        }
        if (! $done) {
            $s->update(['open_work_order_id' => null]);

            return;
        }
        $plan = $s->plan;
        $doneAt = CarbonImmutable::parse(($wo->completed_at ?? now())->copy()->setTimezone(Fmt::tz())->toDateString());
        $meter = $plan->meter_parameter_id ? $this->latestMeter($s->asset_id, $plan->meter_parameter_id) : null;
        $s->update([
            'open_work_order_id' => null,
            'last_done_at' => $doneAt->toDateString(),
            'next_due_at' => $plan->interval_days ? $doneAt->addDays($plan->interval_days)->toDateString() : null,
            'last_meter' => $meter ?? $s->last_meter,
            'next_due_meter' => $plan->interval_meter ? ($meter ?? $s->next_due_meter ?? 0) + $plan->interval_meter : null,
            'done_count' => $s->done_count + 1,
        ]);
    }

    /**
     * Catat pembacaan kondisi; bila melewati batas buat (atau tautkan ke) WO predictive. Pembacaan meter langsung
     * memeriksa PM berbasis meter untuk aset ini.
     */
    public function recordReading(Asset $asset, ConditionParameter $param, float $value, User $by, ?string $note = null, $readAt = null): ConditionReading
    {
        if (! $by->hasPermission('condition.record')) {
            throw WorkflowException::forbidden();
        }
        if ($param->category_id && (int) $param->category_id !== (int) $asset->category_id) {
            throw WorkflowException::invalid('Parameter '.$param->name.' tidak berlaku untuk kategori aset ini.');
        }
        if ($param->is_meter && ($last = $this->latestMeter($asset->id, $param->id)) !== null && $value < $last) {
            throw WorkflowException::invalid('Nilai meter tidak boleh turun (terakhir '.rtrim(rtrim(number_format($last, 3, ',', '.'), '0'), ',').'). Bila meter diganti, catat di keterangan aset.');
        }

        $reading = DB::transaction(function () use ($asset, $param, $value, $by, $note, $readAt) {
            $status = $param->is_meter ? 'normal' : $param->evaluate($value);
            $r = ConditionReading::create([
                'asset_id' => $asset->id, 'condition_parameter_id' => $param->id, 'value' => $value, 'status' => $status,
                'read_at' => $readAt ?? now(), 'recorded_by' => $by->id, 'note' => $note,
            ]);
            if ($status === 'critical' || ($status === 'warning' && $param->wo_on_warning)) {
                $open = WorkOrder::query()->where('asset_id', $asset->id)->where('source', 'predictive')->whereIn('status', WorkOrder::OPEN)
                    ->whereHas('conditionReading', fn ($q) => $q->where('condition_parameter_id', $param->id))->first();
                if ($open) {
                    $r->update(['work_order_id' => $open->id]);
                    if ($status === 'critical' && $open->priority > $param->critical_priority) {
                        $open->update(['priority' => $param->critical_priority]);
                    }
                } else {
                    $priority = $status === 'critical' ? $param->critical_priority : 'P3';
                    $t = $this->sla->targets($priority, now(), $asset->property_id);
                    $limit = $status === 'critical' ? $param->critical_value : $param->warning_value;
                    $wo = WorkOrder::create([
                        'property_id' => $asset->property_id, 'type' => 'predictive', 'source' => 'predictive', 'condition_reading_id' => $r->id,
                        'title' => ($status === 'critical' ? 'Kondisi kritis' : 'Kondisi waspada').': '.$param->name.' '.$r->valueText().' — '.$asset->name,
                        'description' => $param->name.' terbaca '.$r->valueText().' (batas '.($param->direction === 'below' ? '≤ ' : '≥ ').rtrim(rtrim(number_format((float) $limit, 3, ',', '.'), '0'), ',').' '.($param->unit ?? '').') oleh '.$by->name.'.'
                            .($param->guidance ? "\n\nPanduan: ".$param->guidance : '').($note ? "\nCatatan: ".$note : ''),
                        'asset_id' => $asset->id, 'location_id' => $asset->location_id, 'priority' => $priority,
                        'status' => 'draft', 'received_at' => now(), 'response_due_at' => $t['response_due_at'], 'due_at' => $t['due_at'],
                        'sla_calendar' => $t['calendar'], 'created_by' => $by->id,
                    ]);
                    app(WorkOrderService::class)->event($wo, null, 'draft', 'create', 'Dibuat otomatis dari pemantauan kondisi ('.$param->name.')');
                    $r->update(['work_order_id' => $wo->id]);
                    $this->notify->send($this->notify->usersWith('wo.assign'), 'Kondisi '.($status === 'critical' ? 'kritis' : 'waspada').': '.$asset->asset_code,
                        $wo->title, WorkOrderResource::getUrl('view', ['record' => $wo], panel: 'admin'), 'pdm-wo-'.$wo->id, $status === 'critical' ? 'danger' : 'warning');
                }
            }

            return $r;
        });
        if ($param->is_meter) {
            $this->generateDue(null, $asset->id, $by);
        }

        return $reading->fresh(['workOrder']);
    }
}
