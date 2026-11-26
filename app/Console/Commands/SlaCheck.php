<?php

namespace App\Console\Commands;

use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\NotificationService;
use App\Services\SlaService;
use Illuminate\Console\Command;

/** Peringatan SLA: 80% → PIC & Chief Engineering; terlewati → GM. Deduplikasi per objek & siklus. */
class SlaCheck extends Command
{
    protected $signature = 'assetcare:sla-check';

    protected $description = 'Kirim peringatan SLA respons & pemulihan (jalankan tiap menit lewat scheduler)';

    public function handle(SlaService $sla, NotificationService $notify): int
    {
        $warnPct = (float) config('assetcare.sla_warning_pct', 80);
        $ce = $notify->usersWith('wo.assign');
        $gm = User::whereHas('roles', fn ($q) => $q->where('slug', 'gm'))->where('is_active', true)->get();
        $sent = 0;

        // Respons laporan
        foreach (ServiceRequest::where('status', 'submitted')->whereNull('response_at')->whereNotNull('response_due_at')->get() as $r) {
            $pct = $sla->percentUsed($r->received_at, $r->response_due_at, $sla->policy($r->priority, $r->property_id)['calendar']);
            $url = ServiceRequestResource::getUrl('view', ['record' => $r], panel: 'admin');
            if ($pct >= 100) {
                $sent += (int) $notify->send($ce->merge($gm), 'Respons terlewat: '.$r->number.' ('.$r->priority.')', $r->symptom, $url, 'sr-breach-'.$r->id, 'danger');
            } elseif ($pct >= $warnPct) {
                $sent += (int) $notify->send($ce, 'Respons hampir lewat: '.$r->number, $r->symptom, $url, 'sr-warn-'.$r->id, 'warning');
            }
        }

        // Pemulihan work order (hold tidak dihitung; P1 tanpa target pemulihan dilewati)
        foreach (WorkOrder::whereIn('status', ['approved', 'assigned', 'in_progress', 'reopened', 'draft', 'awaiting_approval'])->whereNull('restored_at')->whereNotNull('due_at')->get() as $w) {
            $pct = $sla->percentUsed($w->received_at, $w->due_at, $w->sla_calendar, (int) $w->hold_minutes);
            $url = WorkOrderResource::getUrl('view', ['record' => $w], panel: 'admin');
            $pic = collect([$w->pic])->filter();
            if ($pct >= 100) {
                $sent += (int) $notify->send($pic->merge($ce)->merge($gm), 'Target pemulihan terlewat: '.$w->number, $w->title, $url, 'wo-breach-'.$w->id.'-'.$w->sla_cycle, 'danger');
            } elseif ($pct >= $warnPct) {
                $sent += (int) $notify->send($pic->merge($ce), 'SLA '.round($pct).'%: '.$w->number, $w->title, $url, 'wo-warn-'.$w->id.'-'.$w->sla_cycle, 'warning');
            }
        }
        $this->info($sent.' notifikasi dikirim.');

        return self::SUCCESS;
    }
}
