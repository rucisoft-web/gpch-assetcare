<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ConditionParameter;
use App\Models\PmPlan;
use App\Models\PmSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\PlannedMaintenanceService;
use App\Services\ReportService;
use App\Services\WorkflowException;
use App\Services\WorkOrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Preventive (rencana PM → WO otomatis) dan predictive (kondisi → WO otomatis). */
class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Jalankan WO sampai terverifikasi (CE menugaskan & memverifikasi, teknisi mengerjakan). */
    protected function finish(WorkOrder $wo, array $completeData = []): WorkOrder
    {
        $svc = app(WorkOrderService::class);
        $ce = $this->user('engineering@grandpasundanhotel.com');
        $tech = $this->user('teknisi1@grandpasundanhotel.com');
        $this->actingAs($ce);
        $wo = $svc->run($wo, 'approve_direct', ['reason' => 'PM rutin'], $ce);
        $wo = $svc->run($wo, 'assign', ['pic_id' => $tech->id], $ce);
        $this->actingAs($tech);
        $wo = $svc->run($wo, 'start', [], $tech);
        $wo = $svc->run($wo, 'complete', $completeData + ['diagnosis' => 'Rutin', 'action_taken' => 'Servis sesuai checklist', 'function_test_result' => 'pass', 'no_cost_reason' => 'tenaga internal'], $tech);
        $this->actingAs($ce);

        return $svc->run($wo, 'verify', ['asset_status_after' => 'serviceable'], $ce);
    }

    public function test_pm_plan_generates_wo_once_and_advances_from_completion_date(): void
    {
        $ce = $this->user('engineering@grandpasundanhotel.com');
        $this->actingAs($ce);
        $boiler = Asset::where('legacy_code', 'LEG-BOI-01')->firstOrFail();
        $pm = app(PlannedMaintenanceService::class);
        $s = PmSchedule::whereHas('plan', fn ($q) => $q->where('code', 'PM-BOI-01'))->firstOrFail();
        $due = CarbonImmutable::parse($s->next_due_at->toDateString());

        $this->assertSame(0, $pm->generateDue($due->subDays(8)), 'belum masuk H-7');
        $this->assertSame(1, $pm->generateDue($due->subDays(7)));
        $this->assertSame(0, $pm->generateDue($due), 'tidak dobel selama WO masih terbuka');

        $wo = WorkOrder::findOrFail($s->fresh()->open_work_order_id);
        $this->assertSame('preventive', $wo->type);
        $this->assertSame('pm', $wo->source);
        $this->assertSame($boiler->id, $wo->asset_id);
        $this->assertCount(4, $wo->pm_checklist);

        // checklist belum lengkap & tanpa tindak lanjut → ditolak
        try {
            $this->finish($wo, ['pm_done' => [0, 1]]);
            $this->fail('checklist PM belum lengkap seharusnya ditolak');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('Checklist PM', $e->getMessage());
        }
        $wo = $wo->fresh();
        $this->actingAs($this->user('teknisi1@grandpasundanhotel.com'));
        app(WorkOrderService::class)->run($wo, 'complete', ['pm_done' => [0, 1, 2, 3], 'diagnosis' => 'Rutin', 'action_taken' => 'Blowdown & uji', 'function_test_result' => 'pass', 'no_cost_reason' => 'internal', 'evidence_exception_reason' => 'foto di kartu PM kertas'], $this->user('teknisi1@grandpasundanhotel.com'));
        $this->actingAs($ce);
        app(WorkOrderService::class)->run($wo->fresh(), 'verify', ['asset_status_after' => 'serviceable'], $ce);

        $s = $s->fresh();
        $this->assertNull($s->open_work_order_id);
        $this->assertSame(1, $s->done_count);
        $doneAt = CarbonImmutable::parse($wo->fresh()->completed_at->copy()->setTimezone(\App\Support\Fmt::tz())->toDateString());
        $this->assertSame($doneAt->toDateString(), $s->last_done_at->toDateString());
        $this->assertSame($doneAt->addDays(30)->toDateString(), $s->next_due_at->toDateString());
        $this->assertTrue(collect($wo->fresh()->pm_checklist)->every(fn ($r) => $r['done']));
    }

    public function test_meter_reading_triggers_pm_and_meter_cannot_decrease(): void
    {
        $ce = $this->user('engineering@grandpasundanhotel.com');
        $this->actingAs($ce);
        $pm = app(PlannedMaintenanceService::class);
        $gen = Asset::where('legacy_code', 'LEG-GEN-01')->firstOrFail();
        $jam = ConditionParameter::where('code', 'JAM-OPS')->firstOrFail();
        // data contoh: 1180 → 1442 jam melewati batas 1430 → WO PM sudah dibuat
        $s = PmSchedule::whereHas('plan', fn ($q) => $q->where('code', 'PM-GEN-250'))->firstOrFail();
        $this->assertNotNull($s->open_work_order_id);
        $this->assertEqualsWithDelta(1430, $s->next_due_meter, 0.001);

        $this->expectException(WorkflowException::class);
        $pm->recordReading($gen, $jam, 1400, $ce);
    }

    public function test_condition_reading_creates_single_predictive_wo(): void
    {
        $tech = $this->user('teknisi1@grandpasundanhotel.com');
        $this->actingAs($tech);
        $pm = app(PlannedMaintenanceService::class);
        $boiler = Asset::where('legacy_code', 'LEG-BOI-01')->firstOrFail();
        $p = ConditionParameter::where('code', 'TEK-BOILER')->firstOrFail();

        $normal = $pm->recordReading($boiler, $p, 6.5, $tech);
        $this->assertSame('normal', $normal->status);
        $this->assertNull($normal->work_order_id);

        $warn = $pm->recordReading($boiler, $p, 8.5, $tech);
        $this->assertSame('warning', $warn->status);
        $this->assertNull($warn->work_order_id, 'waspada tanpa wo_on_warning tidak membuat WO');

        $crit = $pm->recordReading($boiler, $p, 10.4, $tech);
        $this->assertSame('critical', $crit->status);
        $wo = $crit->workOrder;
        $this->assertSame('predictive', $wo->type);
        $this->assertSame('P1', $wo->priority);

        $again = $pm->recordReading($boiler, $p, 11, $tech);
        $this->assertSame($wo->id, $again->work_order_id, 'pembacaan kritis berikutnya ditautkan ke WO yang sama');
        $this->assertSame(1, WorkOrder::where('source', 'predictive')->where('asset_id', $boiler->id)->count());

        // parameter kategori lain ditolak; pengguna tanpa izin ditolak
        $chillParam = ConditionParameter::where('code', 'SUHU-CHILL')->firstOrFail();
        try {
            $pm->recordReading($boiler, $chillParam, 3, $tech);
            $this->fail('parameter kategori lain seharusnya ditolak');
        } catch (WorkflowException) {
        }
        $this->expectException(WorkflowException::class);
        $pm->recordReading($boiler, $p, 7, $this->user('fo.supervisor@grandpasundanhotel.com'));
    }

    public function test_cancelled_pm_wo_releases_schedule_without_advancing(): void
    {
        $ce = $this->user('engineering@grandpasundanhotel.com');
        $this->actingAs($ce);
        $s = PmSchedule::whereHas('plan', fn ($q) => $q->where('code', 'PM-CHILL'))->firstOrFail();
        $wo = WorkOrder::findOrFail($s->open_work_order_id);
        $due = $s->next_due_at->toDateString();
        app(WorkOrderService::class)->run($wo, 'cancel', ['reason' => 'digabung dengan servis vendor'], $ce);
        $s = $s->fresh();
        $this->assertNull($s->open_work_order_id);
        $this->assertSame($due, $s->next_due_at->toDateString());
        $this->assertSame(0, $s->done_count);
    }

    public function test_category_defaults_and_pages_and_reports(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $this->actingAs($admin);
        $n = app(PlannedMaintenanceService::class)->plansFromCategoryDefaults($admin);
        $this->assertGreaterThan(0, $n);
        $this->assertSame(0, app(PlannedMaintenanceService::class)->plansFromCategoryDefaults($admin), 'idempoten');

        $plan = PmPlan::where('code', 'PM-GEN-250')->firstOrFail();
        $gen = Asset::where('legacy_code', 'LEG-GEN-01')->firstOrFail();
        $wo = WorkOrder::where('source', 'pm')->firstOrFail();
        foreach (['/admin/pm-plans', '/admin/pm-plans/create', '/admin/pm-plans/'.$plan->id.'/edit', '/admin/condition-parameters', '/admin/condition-readings',
            '/admin/assets/'.$gen->id, '/admin/work-orders/'.$wo->id, '/admin/work-orders'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/work-orders/'.$wo->id)->assertSee('Checklist PM');

        \Livewire\Livewire::test(\App\Filament\Resources\Assets\RelationManagers\ConditionRelationManager::class, ['ownerRecord' => $gen, 'pageClass' => \App\Filament\Resources\Assets\Pages\ViewAsset::class])
            ->assertSuccessful()->assertSee('Jam operasi');
        \Livewire\Livewire::test(\App\Filament\Resources\Assets\RelationManagers\WorkOrdersRelationManager::class, ['ownerRecord' => $gen, 'pageClass' => \App\Filament\Resources\Assets\Pages\ViewAsset::class])
            ->assertSuccessful()->assertSee('Preventive')->filterTable('kelompok', 'terencana')->assertSee($wo->number);
        \Livewire\Livewire::test(\App\Filament\Resources\ConditionReadings\Pages\ListConditionReadings::class)
            ->callAction('catat', ['asset_id' => Asset::where('legacy_code', 'LEG-FRZ-01')->value('id'),
                'condition_parameter_id' => ConditionParameter::where('code', 'SUHU-FREEZ')->value('id'), 'value' => -10])
            ->assertHasNoActionErrors();
        $this->assertTrue(WorkOrder::where('source', 'predictive')->where('asset_id', Asset::where('legacy_code', 'LEG-FRZ-01')->value('id'))->exists());

        \Livewire\Livewire::test(\App\Filament\Resources\PmPlans\Pages\CreatePmPlan::class)
            ->fillForm(['code' => 'PM-PNL', 'name' => 'Thermal scan panel', 'scope' => 'asset', 'asset_id' => Asset::where('legacy_code', 'LEG-PNL-01')->value('id'),
                'wo_type' => 'inspection', 'interval_days' => 90, 'starts_on' => now()->addDays(3)->toDateString(), 'lead_days' => 7, 'priority' => 'P4', 'checklist' => [['item' => 'Scan thermal'], ['item' => 'Kencangkan terminal']]])
            ->call('create')->assertHasNoFormErrors();
        $pnl = PmPlan::where('code', 'PM-PNL')->firstOrFail();
        $this->assertSame(1, $pnl->schedules()->count());
        $this->assertSame(['Scan thermal', 'Kencangkan terminal'], $pnl->checklist);

        $r = app(ReportService::class);
        foreach (['pm_kepatuhan', 'pm_jadwal', 'kondisi', 'riwayat_aset'] as $k) {
            $r->build($k, $admin, now()->subDays(60)->toDateString(), now()->addDays(60)->toDateString());
            $this->get(route('reports.export', ['report' => $k, 'from' => now()->subDays(60)->toDateString(), 'to' => now()->addDays(60)->toDateString()]))->assertOk();
        }
        $this->assertNotEmpty($r->build('pm_jadwal', $admin, now()->toDateString(), now()->toDateString())['rows']);
    }
}
