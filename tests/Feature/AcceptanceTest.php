<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetLabel;
use App\Models\GenerationBatch;
use App\Models\Location;
use App\Models\RoomAssetSlot;
use App\Models\RoomAssetTemplate;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\InstallationService;
use App\Services\LabelService;
use App\Services\QrResolver;
use App\Services\RoomTemplateService;
use App\Services\ServiceRequestService;
use App\Services\TransferService;
use App\Services\WorkflowException;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Uji penerimaan utama (spesifikasi bagian 27) di atas data contoh GPCH. */
class AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function rooms(): array
    {
        return Location::where('location_type', 'room')->where('code', 'like', 'GU-L3-%')->orderBy('sort_key')->pluck('id')->all();
    }

    protected function generate(User $u, ?array $decisions = null): GenerationBatch
    {
        $svc = app(RoomTemplateService::class);
        $tpl = RoomAssetTemplate::where('code', 'TPL-DLX')->firstOrFail();
        $preview = $svc->preview($tpl, $this->rooms());
        $batch = $svc->createBatch($tpl, $this->rooms(), $preview, $u, 'uji-'.count($decisions ?? []));

        return $svc->commit($batch, $decisions ?? [], $u);
    }

    public function test_ac17_ten_rooms_seven_units_give_seventy_candidates(): void
    {
        $u = $this->user('eng.admin@grandpasundanhotel.com');
        $this->actingAs($u);
        $tpl = RoomAssetTemplate::where('code', 'TPL-DLX')->firstOrFail();
        $preview = app(RoomTemplateService::class)->preview($tpl, $this->rooms());
        $this->assertCount(10, $preview);
        $this->assertSame(70, array_sum(array_map(fn ($p) => $p['counts']['standard'], $preview)));
        // kamar 305 punya TV lama → 1 kandidat pencocokan, 69 dibuat baru
        $this->assertSame(1, array_sum(array_map(fn ($p) => $p['counts']['candidate'], $preview)));

        $batch = $this->generate($u);
        $this->assertSame('completed', $batch->status);
        $this->assertSame(70, $batch->created_assets);
        $this->assertSame(70, Asset::where('generation_batch_id', $batch->id)->where('lifecycle_status', 'draft')->where('verification_status', 'unverified')->count());
        $this->assertSame(70, AssetLabel::whereIn('asset_id', Asset::where('generation_batch_id', $batch->id)->pluck('id'))->where('status', 'active')->count());
        // lampu meja posisi Kiri & Kanan
        $this->assertSame(10, Asset::where('generation_batch_id', $batch->id)->where('name', 'Lampu meja Kiri')->count());
    }

    public function test_ac18_resubmitting_same_batch_does_not_duplicate(): void
    {
        $u = $this->user('eng.admin@grandpasundanhotel.com');
        $this->actingAs($u);
        $batch = $this->generate($u);
        $again = app(RoomTemplateService::class)->commit($batch->fresh(), [], $u);
        $this->assertSame($batch->id, $again->id);
        $this->assertSame(70, Asset::where('generation_batch_id', $batch->id)->count());
        // batch baru atas kamar yang sama: semua slot sudah terpetakan → tidak ada aset baru
        $svc = app(RoomTemplateService::class);
        $tpl = RoomAssetTemplate::where('code', 'TPL-DLX')->first();
        $p = $svc->preview($tpl, $this->rooms());
        $this->assertSame(0, array_sum(array_map(fn ($x) => $x['counts']['create'], $p)));
        $this->assertSame(70, array_sum(array_map(fn ($x) => $x['counts']['mapped'], $p)));
    }

    public function test_ac19_room_changed_after_preview_is_rejected_as_conflict(): void
    {
        $u = $this->user('eng.admin@grandpasundanhotel.com');
        $this->actingAs($u);
        $svc = app(RoomTemplateService::class);
        $tpl = RoomAssetTemplate::where('code', 'TPL-DLX')->first();
        $rooms = $this->rooms();
        $preview = $svc->preview($tpl, $rooms);
        $batch = $svc->createBatch($tpl, $rooms, $preview, $u, 'uji-konflik');
        // pengguna lain memproses kamar pertama lebih dulu
        $other = $svc->createBatch($tpl, [$rooms[0]], $svc->preview($tpl, [$rooms[0]]), $u, 'uji-lain');
        $svc->commit($other, [], $u);

        $res = $svc->commit($batch, [], $u);
        $this->assertSame('partial', $res->status);
        $this->assertSame('conflict', $res->results()->where('room_id', $rooms[0])->value('status'));
        $this->assertSame(7, RoomAssetSlot::where('room_id', $rooms[0])->count());
        $this->assertSame(7, Asset::where('location_id', $rooms[0])->count());
    }

    public function test_ac20_matching_old_asset_maps_instead_of_creating(): void
    {
        $u = $this->user('eng.admin@grandpasundanhotel.com');
        $this->actingAs($u);
        $old = Asset::where('legacy_code', 'LEG-TV-305')->firstOrFail();
        $room = $old->location_id;
        $batch = $this->generate($u, [$room.'|tv-utama|1' => $old->id]);
        $this->assertSame(69, $batch->created_assets);
        $this->assertSame(1, $batch->matched_assets);
        $this->assertSame(1, Asset::where('location_id', $room)->where('category_id', $old->category_id)->count());
        $this->assertSame('SN-TV-305-LAMA', $old->fresh()->serial_number);
    }

    public function test_service_request_to_work_order_full_flow_with_controls(): void
    {
        $fo = $this->user('fo.supervisor@grandpasundanhotel.com');
        $ce = $this->user('engineering@grandpasundanhotel.com');
        $gm = $this->user('gm@grandpasundanhotel.com');
        $tech = $this->user('teknisi1@grandpasundanhotel.com');
        $tech2 = $this->user('teknisi2@grandpasundanhotel.com');
        $asset = Asset::where('legacy_code', 'LEG-CHL-01')->firstOrFail();

        $this->actingAs($fo);
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $sr = app(ServiceRequestService::class)->create(['asset_id' => $asset->id, 'category' => 'kitchen', 'symptom' => 'Chiller tidak dingin', 'impact' => 'acara_terganggu'], $fo, $uuid);
        $dup = app(ServiceRequestService::class)->create(['asset_id' => $asset->id, 'category' => 'kitchen', 'symptom' => 'Chiller tidak dingin', 'impact' => 'acara_terganggu'], $fo, $uuid);
        $this->assertSame($sr->id, $dup->id, 'kirim ulang tidak membuat tiket ganda');
        $this->assertSame('P2', $sr->priority);
        $this->assertNotNull($sr->response_due_at);

        $this->actingAs($ce);
        $wos = app(WorkOrderService::class);
        $wo = app(ServiceRequestService::class)->createWorkOrder([$sr->id], ['estimated_cost' => 1500000], $ce);
        $this->assertSame('linked_to_work_order', $sr->fresh()->status);

        $wo = $wos->run($wo, 'submit', [], $ce);
        // pembuat tidak boleh menyetujui sendiri
        try {
            $wos->run($wo, 'approve', [], $ce);
            $this->fail('self approval');
        } catch (WorkflowException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->actingAs($gm);
        $wo = $wos->run($wo, 'approve', [], $gm);
        // versi usang ditolak 409
        try {
            $wos->run($wo, 'assign', ['pic_id' => $tech->id], $gm, 1);
            $this->fail('stale version');
        } catch (WorkflowException $e) {
            $this->assertSame(409, $e->getCode());
        }
        $this->actingAs($ce);
        $wo = $wos->run($wo, 'assign', ['pic_id' => $tech->id, 'team_ids' => [$tech2->id]], $ce, $wo->version);

        $this->actingAs($tech);
        $wo = $wos->run($wo, 'start', [], $tech);
        $this->assertSame('under_repair', $asset->fresh()->operational_status);
        try {
            $wos->run($wo, 'complete', ['diagnosis' => 'Freon bocor'], $tech);
            $this->fail('incomplete');
        } catch (WorkflowException $e) {
            $this->assertSame(422, $e->getCode());
        }
        $wos->addCost($wo, ['type' => 'material', 'description' => 'Freon R404', 'quantity' => 2, 'unit_cost' => 350000], $tech);
        $wo = $wos->run($wo, 'complete', ['diagnosis' => 'Freon bocor', 'action_taken' => 'Las & isi freon', 'function_test_result' => 'pass', 'evidence_exception_reason' => 'Kamera rusak — foto menyusul'], $tech);
        $this->assertSame('completed', $wo->status);

        // pekerjaan kritis (P2): pelaksana tidak boleh memverifikasi sendiri
        try {
            $wos->run($wo, 'verify', ['asset_status_after' => 'serviceable'], $tech);
            $this->fail('self verify');
        } catch (WorkflowException $e) {
            $this->assertContains($e->getCode(), [403]);
        }
        $this->actingAs($ce);
        $wo = $wos->run($wo, 'verify', ['asset_status_after' => 'serviceable'], $ce);
        $this->assertSame('resolved', $sr->fresh()->status);
        $this->assertSame('serviceable', $asset->fresh()->operational_status);
        $wo = $wos->run($wo, 'close', [], $ce);
        $this->assertSame('closed', $wo->status);
        $this->assertSame('closed', $sr->fresh()->status);
        $this->assertEquals(700000, $wo->totalCost());
        $this->assertGreaterThanOrEqual(8, $wo->statusEvents()->count());

        $wo = $wos->run($wo, 'reopen', ['reason' => 'Bocor lagi'], $ce);
        $this->assertSame(2, $wo->sla_cycle);
        $this->assertSame('linked_to_work_order', $sr->fresh()->status);
    }

    public function test_labels_pdf_qr_resolution_and_installation(): void
    {
        $u = $this->user('eng.admin@grandpasundanhotel.com');
        $this->actingAs($u);
        $batch = $this->generate($u);
        $labels = app(LabelService::class)->select(['generation_batch_id' => $batch->id, 'include_unverified' => true]);
        $this->assertCount(70, $labels);
        $lay = app(LabelService::class)->layout(70, 'a4-50x30', 5);
        $this->assertSame(3, $lay['sheets']); // 27 per lembar, mulai sel 5 → 74 posisi
        $pb = app(LabelService::class)->createBatch($labels, 'a4-50x30', 5, $u, ['generation_batch_id' => $batch->id]);
        $this->assertSame(70, $pb->items()->count());
        $pdf = app(LabelService::class)->pdf($pb)->output();
        $this->assertStringStartsWith('%PDF', $pdf);

        // QR: host asing ditolak, token valid → aset, rotasi → label lama tidak berlaku
        $label = $labels->first();
        $r = app(QrResolver::class)->resolve($label->url());
        $this->assertSame('asset', $r['type']);
        $this->assertSame('foreign', app(QrResolver::class)->resolve('https://evil.example/q/'.$label->token())['type']);
        $this->assertSame('asset', app(QrResolver::class)->resolve(strtolower($label->asset->asset_code))['type']);

        // pemasangan: ruang salah ditolak, ruang benar tercatat sekali
        $room = $label->asset->location;
        $wrong = Location::where('code', 'GU-L3-310')->first()->is($room) ? Location::where('code', 'GU-L3-301')->first() : Location::where('code', 'GU-L3-310')->first();
        $this->assertFalse(app(InstallationService::class)->confirm($wrong, $label->url(), $u)['ok']);
        $ok = app(InstallationService::class)->confirm($room, $label->url(), $u);
        $this->assertTrue($ok['ok']);
        $again = app(InstallationService::class)->confirm($room, $label->url(), $u);
        $this->assertSame('info', $again['level']);
        $this->assertSame('installed', $label->asset->fresh()->installation_status);

        $new = app(LabelService::class)->rotate($label, 'Uji rotasi', $u);
        $this->assertSame('revoked', app(QrResolver::class)->resolve($label->url())['type']);
        $this->assertSame('asset', app(QrResolver::class)->resolve($new->url())['type']);
    }

    public function test_ac27_transfer_updates_location_releases_slot_and_flags_relabel(): void
    {
        $ac = $this->user('eng.admin@grandpasundanhotel.com');
        $this->actingAs($ac);
        $batch = $this->generate($ac);
        $asset = Asset::where('generation_batch_id', $batch->id)->where('name', 'TV utama')->first();
        $asset->forceFill(['verification_status' => 'verified', 'condition_grade' => 'B', 'custodian_unit' => 'Engineering'])->save();
        $asset->forceFill(['lifecycle_status' => 'active'])->save();
        $labels = app(LabelService::class)->select(['asset_ids' => [$asset->id], 'include_unverified' => true]);
        app(LabelService::class)->createBatch($labels, 'a4-50x30', 1, $ac);
        $to = Location::where('code', 'like', 'GU-L3-31%')->orWhere('location_type', 'area')->first();

        $t = \App\Models\AssetTransfer::create(['to_location_id' => $to->id, 'reason' => 'Pindah ke ruang lain', 'status' => 'draft', 'requested_by' => $ac->id]);
        $t->items()->create(['asset_id' => $asset->id, 'from_location_id' => $asset->location_id, 'condition_out' => 'B']);
        $svc = app(TransferService::class);
        $svc->submit($t, $ac);
        try {
            $svc->approve($t, $ac);
            $this->fail('self approve transfer');
        } catch (WorkflowException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $gm = $this->user('gm@grandpasundanhotel.com');
        $this->actingAs($gm);
        $svc->approve($t, $gm);
        $this->actingAs($ac);
        $svc->dispatch($t, $ac);
        $this->assertTrue((bool) $asset->fresh()->in_transit);
        $svc->receive($t, [], $ac);
        $asset->refresh();
        $this->assertSame($to->id, $asset->location_id);
        $this->assertFalse((bool) $asset->in_transit);
        $this->assertSame('needs_relabel', $asset->installation_status);
        $this->assertFalse(\App\Models\SlotAssignment::where('active_asset_key', $asset->id)->exists());
    }

    public function test_access_is_denied_by_default_and_reporter_cannot_see_register(): void
    {
        $hk = $this->user('hk.supervisor@grandpasundanhotel.com');
        $hk->forceFill(['must_change_password' => false])->save();
        $this->actingAs($hk);
        $this->assertTrue($hk->hasPermission('request.create'));
        $this->assertFalse($hk->hasPermission('asset.view_financial'));
        $this->assertFalse($hk->hasPermission('wo.approve'));
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/service-requests/create')->assertOk();
    }
}
