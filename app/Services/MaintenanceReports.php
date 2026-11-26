<?php

namespace App\Services;

use App\Models\ConditionReading;
use App\Models\PmSchedule;
use App\Models\WorkOrder;
use App\Support\Fmt;
use Carbon\CarbonImmutable;

/** Laporan pemeliharaan terencana: kepatuhan PM, jadwal mendatang, kondisi di luar batas, riwayat per aset. */
class MaintenanceReports
{
    /** @return array{headers:array, rows:array} */
    public function build(string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($report) {
            'pm_kepatuhan' => $this->compliance($from, $to),
            'pm_jadwal' => $this->upcoming(),
            'kondisi' => $this->abnormal($from, $to),
            'riwayat_aset' => $this->history($from, $to),
        };
    }

    /** WO preventive/inspeksi dengan jatuh tempo pada periode: tepat waktu bila selesai ≤ jatuh tempo. */
    protected function compliance(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = Fmt::tz();
        $rows = WorkOrder::query()->where('source', 'pm')
            ->whereBetween('planned_due_at', [$from->setTimezone($tz)->toDateString(), $to->setTimezone($tz)->toDateString()])
            ->with('asset', 'pmSchedule.plan', 'pic')->orderBy('planned_due_at')->get()
            ->map(function ($w) use ($tz) {
                $done = $w->completed_at?->copy()->setTimezone($tz);
                $state = $w->status === 'cancelled' ? 'Dibatalkan'
                    : ($done ? ($done->toDateString() <= $w->planned_due_at->toDateString() ? 'Tepat waktu' : 'Terlambat '.$w->planned_due_at->diffInDays($done->startOfDay()).' hari')
                        : ($w->planned_due_at->isPast() ? 'Belum selesai (lewat)' : 'Belum selesai'));
                $list = collect($w->pm_checklist ?? []);

                return [$w->number, $w->pmSchedule?->plan?->code, $w->asset?->asset_code, $w->asset?->name, $w->planned_due_at->format('d/m/Y'),
                    $done?->format('d/m/Y'), $state, $list->isEmpty() ? '' : $list->where('done', true)->count().'/'.$list->count(), $w->pic?->name, WorkOrder::STATUSES[$w->status] ?? $w->status];
            });
        $due = $rows->reject(fn ($r) => $r[6] === 'Dibatalkan')->count();
        $onTime = $rows->where(6, 'Tepat waktu')->count();
        $rows->push(['Kepatuhan PM', '', '', '', '', '', $due ? round($onTime / $due * 100, 1).'% tepat waktu ('.$onTime.'/'.$due.')' : 'N/A', '', '', '']);

        return ['headers' => ['WO', 'Rencana', 'Kode aset', 'Aset', 'Jatuh tempo', 'Selesai', 'Ketepatan', 'Checklist', 'PIC', 'Status'], 'rows' => $rows->all()];
    }

    protected function upcoming(): array
    {
        $limit = now(Fmt::tz())->addDays(60)->toDateString();
        $rows = PmSchedule::query()->whereHas('plan', fn ($q) => $q->where('is_active', true))->whereHas('asset')
            ->where(fn ($q) => $q->whereNull('next_due_at')->orWhere('next_due_at', '<=', $limit))
            ->with('plan.meterParameter', 'asset.location', 'openWorkOrder')->get()
            ->sortBy(fn ($s) => $s->next_due_at?->toDateString() ?? '9999')
            ->map(fn ($s) => [$s->plan->code, $s->plan->name, $s->asset->asset_code, $s->asset->name, $s->asset->location?->full_path, $s->plan->intervalText(),
                $s->last_done_at?->format('d/m/Y'), $s->next_due_at?->format('d/m/Y'), $s->next_due_meter, $s->openWorkOrder?->number,
                $s->next_due_at && $s->next_due_at->isPast() && ! $s->open_work_order_id ? 'LEWAT' : ''])->values();

        return ['headers' => ['Rencana', 'Pekerjaan', 'Kode aset', 'Aset', 'Lokasi', 'Interval', 'Terakhir', 'Jatuh tempo', 'Jatuh tempo meter', 'WO terbuka', 'Catatan'], 'rows' => $rows->all()];
    }

    protected function abnormal(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'headers' => ['Waktu', 'Kode aset', 'Aset', 'Parameter', 'Nilai', 'Batas', 'Status', 'WO', 'Status WO', 'Dicatat'],
            'rows' => ConditionReading::query()->whereIn('status', ['warning', 'critical'])->whereBetween('read_at', [$from, $to])->whereHas('asset')
                ->with('asset', 'parameter', 'workOrder', 'recorder')->orderBy('read_at')->get()
                ->map(fn ($r) => [Fmt::dt($r->read_at), $r->asset->asset_code, $r->asset->name, $r->parameter?->name, $r->valueText(), $r->parameter?->limitText(),
                    ConditionReading::STATUSES[$r->status], $r->workOrder?->number, $r->workOrder ? (WorkOrder::STATUSES[$r->workOrder->status] ?? '') : '', $r->recorder?->name])->all(),
        ];
    }

    /** Ringkasan pemeliharaan per aset pada periode: insidental vs terencana vs predictive. */
    protected function history(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = WorkOrder::query()->whereNotNull('asset_id')->whereBetween('received_at', [$from, $to])->with('asset.category')->get()
            ->groupBy('asset_id')->map(function ($g) {
                $a = $g->first()->asset;
                $done = $g->whereNotNull('completed_at');
                $gross = $done->whereIn('type', ['corrective', 'emergency'])->filter(fn ($w) => $w->restored_at)->sum(fn ($w) => $w->received_at->diffInMinutes($w->restored_at));

                return [$a?->asset_code, $a?->name, $a?->category?->name,
                    $g->whereIn('type', ['corrective', 'emergency'])->count(), $g->whereIn('type', ['preventive', 'inspection'])->count(), $g->where('type', 'predictive')->count(),
                    $g->whereIn('status', WorkOrder::OPEN)->count(), round($gross / 60, 1)];
            })->sortByDesc(3)->values();

        return ['headers' => ['Kode', 'Aset', 'Kategori', 'Insidental', 'Preventive / inspeksi', 'Predictive', 'Masih terbuka', 'Jam gangguan (insidental)'], 'rows' => $rows->all()];
    }
}
