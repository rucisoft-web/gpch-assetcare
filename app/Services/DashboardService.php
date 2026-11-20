<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatusEvent;
use App\Models\AssetTransfer;
use App\Models\RoomBlock;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WoCostLine;
use App\Models\WorkOrder;
use Carbon\CarbonInterface;

/**
 * Indikator (bagian 14). Setiap angka dapat ditelusuri ke daftar sumber; pembagi nol → N/A beserta alasan.
 * Tanggal operasional Asia/Jakarta; timestamp tersimpan UTC.
 */
class DashboardService
{
    public function __construct(protected SlaService $sla) {}

    public function period(int $days = 30): array
    {
        $tz = config('assetcare.display_timezone');
        $to = now();
        $from = now()->timezone($tz)->subDays($days - 1)->startOfDay()->utc();

        return [$from, $to];
    }

    public function myTasks(User $u): array
    {
        $wo = WorkOrder::query()->with('location', 'asset');

        return [
            'assigned' => (clone $wo)->whereIn('status', ['assigned', 'in_progress', 'on_hold', 'reopened'])
                ->where(fn ($q) => $q->where('pic_id', $u->id)->orWhereJsonContains('team_ids', $u->id)->orWhereJsonContains('team_ids', (string) $u->id))
                ->orderByRaw("FIELD(priority,'P1','P2','P3','P4')")->orderBy('due_at')->limit(15)->get(),
            'to_verify' => $u->hasPermission('wo.verify') ? (clone $wo)->where('status', 'completed')->orderBy('completed_at')->limit(10)->get() : collect(),
            'to_approve' => $u->hasPermission('wo.approve') ? (clone $wo)->where('status', 'awaiting_approval')->where('created_by', '!=', $u->id)->limit(10)->get() : collect(),
            'to_triage' => $u->hasPermission('request.triage') ? ServiceRequest::with('location', 'asset')->where('status', 'submitted')->orderByRaw("FIELD(priority,'P1','P2','P3','P4')")->orderBy('received_at')->limit(15)->get() : collect(),
            'transfers' => $u->hasPermission('transfer.approve') ? AssetTransfer::where('status', 'submitted')->where('requested_by', '!=', $u->id)->limit(10)->get() : collect(),
            'my_requests' => ServiceRequest::with('location')->where('reporter_id', $u->id)->whereIn('status', ServiceRequest::OPEN)->latest('received_at')->limit(8)->get(),
            'room_blocks' => $u->hasPermission('room.block_confirm') ? RoomBlock::with('room', 'workOrder')->where('status', 'proposed')->get() : collect(),
        ];
    }

    public function kpis(?CarbonInterface $from = null, ?CarbonInterface $to = null, bool $financial = false): array
    {
        [$dFrom, $dTo] = $this->period();
        $from ??= $dFrom;
        $to ??= $dTo;

        // Backlog: WO terbuka pada waktu laporan, umur sejak dibuat
        $open = WorkOrder::whereIn('status', WorkOrder::OPEN)->get(['id', 'created_at', 'due_at', 'restored_at', 'priority', 'status']);
        $aging = ['0–7 hari' => 0, '8–30 hari' => 0, '>30 hari' => 0];
        foreach ($open as $w) {
            $age = $w->created_at->diffInDays(now());
            $aging[$age <= 7 ? '0–7 hari' : ($age <= 30 ? '8–30 hari' : '>30 hari')]++;
        }
        $overdueWo = $open->filter(fn ($w) => $w->due_at && ! $w->restored_at && $w->due_at->isPast())->count();

        // Respons sesuai SLA: cohort laporan yang sudah jatuh tempo respons; yang belum direspons ikut gagal
        $cohort = ServiceRequest::whereBetween('received_at', [$from, $to])->whereNotNull('response_due_at')->where('response_due_at', '<=', now())->get(['response_at', 'response_due_at']);
        $met = $cohort->filter(fn ($r) => $r->response_at && $r->response_at->lessThanOrEqualTo($r->response_due_at))->count();
        $sla = $cohort->count() ? ['value' => round($met / $cohort->count() * 100, 1), 'note' => $met.' dari '.$cohort->count().' laporan'] : ['value' => null, 'note' => 'N/A — belum ada laporan yang jatuh tempo respons pada periode'];

        // MTTR: rata-rata durasi pemulihan (bruto) kegagalan yang pulih pada periode
        $restored = WorkOrder::whereBetween('restored_at', [$from, $to])->whereIn('type', ['corrective', 'emergency'])->get(['received_at', 'restored_at', 'hold_minutes']);
        $mttr = $restored->count() ? ['value' => round($restored->avg(fn ($w) => $w->received_at->diffInMinutes($w->restored_at))), 'net' => round($restored->avg(fn ($w) => max(0, $w->received_at->diffInMinutes($w->restored_at) - $w->hold_minutes))), 'note' => $restored->count().' kejadian pulih'] : ['value' => null, 'net' => null, 'note' => 'N/A — belum ada kegagalan yang pulih pada periode'];

        // Repeat failure: WO baru pada aset & failure mode sama ≤ N hari setelah pemulihan
        $window = (int) config('assetcare.repeat_failure_days', 30);
        $repeat = WorkOrder::whereBetween('received_at', [$from, $to])->whereNotNull('asset_id')->whereNotNull('failure_mode')->get(['id', 'asset_id', 'failure_mode', 'received_at'])
            ->filter(fn ($w) => WorkOrder::where('asset_id', $w->asset_id)->where('failure_mode', $w->failure_mode)->where('id', '!=', $w->id)
                ->whereNotNull('restored_at')->whereBetween('restored_at', [$w->received_at->copy()->subDays($window), $w->received_at])->exists())->count();

        $assets = Asset::query();
        $out = [
            'period' => [$from, $to],
            'open_wo' => $open->count(),
            'overdue_wo' => $overdueWo,
            'p1p2_open' => $open->whereIn('priority', ['P1', 'P2'])->count(),
            'aging' => $aging,
            'sla_response' => $sla,
            'mttr' => $mttr,
            'repeat_failures' => $repeat,
            'downtime_hours' => $this->downtimeHours($from, $to),
            'room_days' => $this->roomDays($from, $to),
            'requests_open' => ServiceRequest::whereIn('status', ServiceRequest::OPEN)->count(),
            'requests_untriaged' => ServiceRequest::where('status', 'submitted')->count(),
            'assets_total' => (clone $assets)->whereIn('lifecycle_status', ['draft', 'active'])->count(),
            'assets_active' => (clone $assets)->where('lifecycle_status', 'active')->count(),
            'assets_draft' => (clone $assets)->where('lifecycle_status', 'draft')->count(),
            'assets_unverified' => (clone $assets)->whereIn('lifecycle_status', ['draft', 'active'])->where('verification_status', '!=', 'verified')->count(),
            'assets_out' => (clone $assets)->whereIn('operational_status', ['out_of_service', 'under_repair'])->where('lifecycle_status', 'active')->count(),
            'assets_de' => (clone $assets)->whereIn('condition_grade', ['D', 'E'])->whereIn('lifecycle_status', ['draft', 'active'])->count(),
            'conditions' => (clone $assets)->whereIn('lifecycle_status', ['draft', 'active'])->selectRaw("COALESCE(condition_grade,'?') g, COUNT(*) n")->groupBy('g')->pluck('n', 'g')->all(),
            'labels_pending' => (clone $assets)->whereIn('lifecycle_status', ['draft', 'active'])->whereIn('installation_status', ['pending', 'needs_relabel', 'damaged', 'missing'])->count(),
            'warranty_90' => (clone $assets)->whereBetween('warranty_until', [now()->toDateString(), now()->addDays(90)->toDateString()])->count(),
            'pm_on_time' => ['value' => null, 'note' => 'N/A — modul preventive maintenance masuk rilis B'],
        ];
        if ($financial) {
            $lines = WoCostLine::whereBetween('created_at', [$from, $to])->get(['amount', 'cost_status', 'is_cash_out']);
            $out['cost'] = [
                'provisional' => (float) $lines->where('cost_status', 'provisional')->sum('amount'),
                'final' => (float) $lines->where('cost_status', 'final')->sum('amount'),
                'cash_out' => (float) $lines->where('is_cash_out', true)->sum('amount'),
                'internal' => (float) $lines->where('is_cash_out', false)->sum('amount'),
            ];
        }

        return $out;
    }

    /** Gabungan interval out_of_service per aset dalam periode (tumpang tindih dihitung sekali). */
    public function downtimeHours(CarbonInterface $from, CarbonInterface $to): float
    {
        $events = AssetStatusEvent::where('dimension', 'operational_status')->where('occurred_at', '<=', $to)->orderBy('asset_id')->orderBy('occurred_at')->orderBy('id')->get(['asset_id', 'new_value', 'occurred_at']);
        $total = 0;
        foreach ($events->groupBy('asset_id') as $list) {
            $start = null;
            $intervals = [];
            foreach ($list as $e) {
                if ($e->new_value === 'out_of_service' && ! $start) {
                    $start = $e->occurred_at;
                } elseif ($e->new_value !== 'out_of_service' && $start) {
                    $intervals[] = [$start, $e->occurred_at];
                    $start = null;
                }
            }
            if ($start) {
                $intervals[] = [$start, $to];
            }
            $total += $this->mergedMinutes($intervals, $from, $to);
        }

        return round($total / 60, 1);
    }

    /** Room days terdampak: jam blokir kamar terkonfirmasi ÷ 24, per kamar digabung (tidak menjumlah beberapa aset). */
    public function roomDays(CarbonInterface $from, CarbonInterface $to): float
    {
        $total = 0;
        foreach (RoomBlock::where('status', 'confirmed')->orWhere(fn ($q) => $q->where('status', 'released')->whereNotNull('fo_confirmed_at'))->get()->groupBy('room_id') as $blocks) {
            $total += $this->mergedMinutes($blocks->map(fn ($b) => [$b->started_at ?? $b->fo_confirmed_at, $b->ended_at ?? $to])->all(), $from, $to);
        }

        return round($total / 60 / 24, 2);
    }

    protected function mergedMinutes(array $intervals, CarbonInterface $from, CarbonInterface $to): int
    {
        $clipped = [];
        foreach ($intervals as [$s, $e]) {
            if (! $s) {
                continue;
            }
            $s = $s->greaterThan($from) ? $s : $from;
            $e = $e->lessThan($to) ? $e : $to;
            if ($e->greaterThan($s)) {
                $clipped[] = [$s->getTimestamp(), $e->getTimestamp()];
            }
        }
        usort($clipped, fn ($a, $b) => $a[0] <=> $b[0]);
        $sum = 0;
        $cur = null;
        foreach ($clipped as $iv) {
            if (! $cur) {
                $cur = $iv;
            } elseif ($iv[0] <= $cur[1]) {
                $cur[1] = max($cur[1], $iv[1]);
            } else {
                $sum += $cur[1] - $cur[0];
                $cur = $iv;
            }
        }
        if ($cur) {
            $sum += $cur[1] - $cur[0];
        }

        return intdiv($sum, 60);
    }
}
