<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDocument;
use App\Models\DocumentType;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\DepreciationService;
use App\Services\DocumentService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kebijakan penyusutan GPCH (dua buku) dan register dokumen aset. */
class FixedAssetDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function admin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
    }

    public function test_policy_example_ffe_w_70jt_two_books(): void
    {
        $this->actingAs($this->admin());
        $a = Asset::where('legacy_code', 'LEG-PRJ-01')->firstOrFail();
        $a->update(['category_id' => AssetCategory::whereHas('depreciationClass', fn ($q) => $q->where('code', 'FFE-W'))->value('id')]);
        $a->financial()->update(['acquisition_cost' => 70000000, 'acquisition_date' => '2026-01-10', 'opening_balance_date' => null,
            'opening_accum_commercial' => null, 'opening_accum_fiscal' => null, 'depreciation_class_id' => null, 'fiscal_group_code' => null]);
        $s = app(DepreciationService::class)->yearSummary($a->fresh(), 2026);
        $this->assertSame('FFE-W', $s['params']['class']->code);
        $this->assertEqualsWithDelta(10000000, $s['commercial']['depreciation'], 0.01); // 7 tahun
        $this->assertEqualsWithDelta(17500000, $s['fiscal']['depreciation'], 0.01);     // Kelompok 1, 4 tahun
        $this->assertEqualsWithDelta(-7500000, $s['correction'], 0.01);                  // koreksi fiskal negatif
    }

    public function test_vehicle_residual_disposal_month_and_opening_balance(): void
    {
        $this->actingAs($this->admin());
        $svc = app(DepreciationService::class);
        $vehClass = \App\Models\DepreciationClass::where('code', 'VEH')->firstOrFail();
        $a = Asset::where('legacy_code', 'LEG-SND-01')->firstOrFail();
        $a->financial()->update(['acquisition_cost' => 400000000, 'acquisition_date' => '2026-01-05', 'depreciation_class_id' => $vehClass->id, 'disposal_date' => null]);
        $p = $svc->params($a->fresh());
        $this->assertEqualsWithDelta(40000000, $p['residual'], 0.01); // 10%
        $this->assertEqualsWithDelta(3750000, $svc->monthly($p, 'commercial'), 0.01); // 360jt / 96

        // dilepas Juni: Januari–Mei saja yang disusutkan
        $a->financial()->update(['disposal_date' => '2026-06-20']);
        $p = $svc->params($a->fresh());
        $this->assertEqualsWithDelta(5 * 3750000, $svc->accumulated($p, 'commercial', CarbonImmutable::create(2026, 12, 1)), 0.01);

        // saldo awal migrasi: genset disusutkan prospektif atas sisa umur
        $g = Asset::where('legacy_code', 'LEG-GEN-01')->firstOrFail();
        $pg = $svc->params($g);
        $acc = $svc->accumulated($pg, 'commercial', CarbonImmutable::create(2026, 12, 1));
        $remaining = 180 - 82;
        $this->assertEqualsWithDelta(387222222 + (850000000 - 387222222) * 12 / $remaining, $acc, 0.05);
    }

    public function test_every_category_is_mapped_and_policy_is_configurable(): void
    {
        $this->assertSame(0, AssetCategory::whereNull('depreciation_class_id')->count());
        $policy = \App\Models\FixedAssetPolicy::current();
        $this->assertSame(40, (int) $policy->building_fiscal_life_years);
        $policy->update(['building_fiscal_life_years' => 20]);
        $this->assertSame(240, \App\Models\FiscalGroup::lifeFor('BP', $policy->fresh()));
    }

    public function test_document_status_reminders_dedupe_and_k3_block(): void
    {
        $ce = $this->user('engineering@grandpasundanhotel.com');
        $this->actingAs($ce);
        $boiler = Asset::where('legacy_code', 'LEG-BOI-01')->firstOrFail();
        $this->assertTrue($boiler->operationBlocked(), 'riksa uji boiler contoh sudah kedaluwarsa');

        $svc = app(DocumentService::class);
        $today = CarbonImmutable::now(\App\Support\Fmt::tz())->startOfDay();
        $first = $svc->sendReminders($today);
        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $svc->sendReminders($today), 'titik pengingat yang sama tidak dikirim dua kali');

        // dokumen K3 baru terbit → aset boleh beroperasi lagi, dokumen lama "Diganti"
        $old = $boiler->documents()->whereHas('type', fn ($t) => $t->where('code', 'K3-03'))->firstOrFail();
        $new = $svc->replace($old, ['issued_at' => $today->toDateString(), 'valid_until' => $today->addYear()->toDateString()], $ce);
        $this->assertSame('replaced', $old->fresh()->status);
        $this->assertSame('valid', $new->status);
        $this->assertFalse($boiler->fresh()->operationBlocked());

        // perpanjangan diajukan menghentikan pengingat selama belum lewat
        $doc = AssetDocument::whereHas('type', fn ($t) => $t->where('code', 'K3-02'))->firstOrFail();
        $svc->startRenewal($doc, ['renewal_ref' => 'SURAT-01']);
        $this->assertSame('renewing', $doc->fresh()->status);
    }

    public function test_asset_without_documents_and_missing_report(): void
    {
        $this->actingAs($this->admin());
        $tv = Asset::where('legacy_code', 'LEG-TV-305')->firstOrFail();
        $this->assertSame(0, $tv->documents()->count());
        $rows = app(DocumentService::class)->missingDocuments();
        $this->assertTrue($rows->contains(fn ($r) => $r['asset']->legacy_code === 'LEG-GEN-01'));
    }

    public function test_classification_and_report_permissions(): void
    {
        $hod = User::whereHas('roles', fn ($q) => $q->where('slug', 'hod'))->first()
            ?? tap($this->user('hk.supervisor@grandpasundanhotel.com'))->roles()->syncWithoutDetaching([\App\Models\Role::where('slug', 'hod')->value('id')]);
        $hod = $hod->fresh();
        $restricted = AssetDocument::where('classification', 'terbatas')->firstOrFail();
        $this->assertFalse($restricted->canBeSeenBy($hod));
        $this->assertTrue($restricted->canBeSeenBy($this->user('ca@grandpasundanhotel.com')));

        $reports = app(ReportService::class);
        $finance = $this->user('ca@grandpasundanhotel.com');
        $this->assertArrayHasKey('rekonsiliasi', $reports->available($finance));
        if (! $hod->canSeeFinancials()) {
            $this->assertArrayNotHasKey('rekonsiliasi', $reports->available($hod));
        }
    }

    public function test_new_pages_render(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $doc = AssetDocument::firstOrFail();
        $asset = Asset::where('legacy_code', 'LEG-GEN-01')->firstOrFail();
        foreach (['/admin/asset-documents', '/admin/asset-documents/create', '/admin/asset-documents/'.$doc->id, '/admin/asset-documents/'.$doc->id.'/edit',
            '/admin/depreciation-classes', '/admin/fiscal-groups', '/admin/document-types', '/admin/asset-categories',
            '/admin/assets/'.$asset->id, '/admin/assets/'.$asset->id.'/edit'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/assets/'.Asset::where('legacy_code', 'LEG-BOI-01')->value('id'))->assertSee('Tidak boleh dioperasikan');
        foreach (array_keys(ReportService::REPORTS) as $k) {
            $this->get(route('reports.export', ['report' => $k, 'from' => '2026-01-01', 'to' => '2026-12-31']))->assertOk();
        }
    }

    public function test_livewire_document_flow_create_upload_renew_replace(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $ce = $this->user('engineering@grandpasundanhotel.com');
        $this->actingAs($ce);
        $asset = Asset::where('legacy_code', 'LEG-PNL-01')->firstOrFail();
        $type = DocumentType::where('code', 'K3-02')->firstOrFail();
        $file = \Illuminate\Http\UploadedFile::fake()->create('slo.pdf', 120, 'application/pdf');

        \Livewire\Livewire::test(\App\Filament\Resources\AssetDocuments\Pages\CreateAssetDocument::class)
            ->fillForm(['document_type_id' => $type->id, 'title' => 'SLO panel LVMDP', 'issued_at' => '2026-01-10', 'valid_until' => '2031-01-10',
                'pic_id' => $ce->id, 'asset_ids' => [$asset->id], 'files' => [$file]])
            ->call('create')->assertHasNoFormErrors();
        $doc = AssetDocument::where('title', 'SLO panel LVMDP')->firstOrFail();
        $this->assertSame([$asset->id], $doc->assets()->pluck('assets.id')->all());
        $this->assertSame(1, $doc->files()->count());
        $this->assertSame(64, strlen($doc->files()->first()->checksum));
        $this->assertStringStartsWith('LGL-IZN/', $doc->register_no);

        $page = \Livewire\Livewire::test(\App\Filament\Resources\AssetDocuments\Pages\ViewAssetDocument::class, ['record' => $doc->getRouteKey()]);
        $page->callAction('unggah', ['file' => \Illuminate\Http\UploadedFile::fake()->create('slo-v2.pdf', 80, 'application/pdf'), 'note' => 'scan ulang'])->assertHasNoActionErrors();
        $this->assertSame(2, (int) $doc->files()->max('file_version'));
        $page->callAction('perpanjang', ['renewal_ref' => 'REQ-1'])->assertHasNoActionErrors();
        $this->assertSame('renewing', $doc->fresh()->status);
        $page->callAction('ganti', ['issued_at' => '2031-01-05', 'valid_until' => '2036-01-05'])->assertHasNoActionErrors();
        $this->assertSame('replaced', $doc->fresh()->status);

        // filter & relation manager dokumen pada kartu aset
        \Livewire\Livewire::test(\App\Filament\Resources\AssetDocuments\Pages\ListAssetDocuments::class)
            ->filterTable('due90')->assertSuccessful()->filterTable('k3')->assertSuccessful();
        \Livewire\Livewire::test(\App\Filament\Resources\Assets\RelationManagers\DocumentsRelationManager::class, ['ownerRecord' => $asset, 'pageClass' => \App\Filament\Resources\Assets\Pages\ViewAsset::class])
            ->assertSuccessful()->assertSee('SLO panel LVMDP');
    }

    public function test_policy_settings_action(): void
    {
        $this->actingAs($this->user('ca@grandpasundanhotel.com'));
        \Livewire\Livewire::test(\App\Filament\Resources\DepreciationClasses\Pages\ManageDepreciationClasses::class)
            ->callAction('kebijakan', ['capitalisation_threshold' => 10000000, 'group_purchase_threshold' => 25000000, 'renovation_threshold' => 25000000,
                'building_fiscal_life_years' => 20, 'deferred_tax_rate' => 22, 'status' => 'approved'])
            ->assertHasNoActionErrors();
        $p = \App\Models\FixedAssetPolicy::current();
        $this->assertSame(20, (int) $p->building_fiscal_life_years);
        $this->assertSame('approved', $p->status);
        $this->assertNotNull($p->approved_by);
    }

    public function test_asset_map_tree_and_type_spread(): void
    {
        User::query()->update(['must_change_password' => false]);
        $this->actingAs($this->user('ca@grandpasundanhotel.com'));
        $this->get('/admin/peta-aset')->assertOk()->assertSee('Pohon lokasi');
        $genset = Asset::where('legacy_code', 'LEG-GEN-01')->firstOrFail();
        $building = collect($genset->location->ancestors())->first() ?? $genset->location;

        // klik gedung → aset di semua sub-lokasinya ikut
        \Livewire\Livewire::test(\App\Filament\Pages\PetaAset::class)
            ->call('pickLocation', $building->id)->assertSee($genset->asset_code)->assertSee($genset->name)
            ->call('pickLocation', $genset->location_id)->assertSee($genset->name)->assertDontSee('Projector Malibu Dome');

        // klik jenis → di lokasi mana saja
        \Livewire\Livewire::test(\App\Filament\Pages\PetaAset::class)
            ->call('setMode', 'jenis')->call('pickCategory', $genset->category_id)
            ->assertSee('Ada di mana saja')->assertSee($genset->location->full_path)
            ->call('showInTree', $genset->location_id)->assertSet('mode', 'lokasi')->assertSee($genset->name);

    }
}
