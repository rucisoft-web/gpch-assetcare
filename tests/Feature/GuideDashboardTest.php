<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetDocuments\Pages\ListAssetDocuments;
use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\ExecutiveDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Tombol bantuan, panduan implementasi, dashboard pimpinan, unggah banyak dokumen legalitas. */
class GuideDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
    }

    public function test_help_button_guide_and_dashboard_render(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/assets')->assertOk()->assertSee('ac-help-btn', false);
        $this->get('/admin/panduan-implementasi')->assertOk()->assertSee('Data awal');
        $this->get('/admin/dashboard-pimpinan')->assertOk()->assertSee('ac-exec', false);

        $keys = array_keys(require app_path('Support/Help/content.php'));
        $this->assertGreaterThan(20, count($keys));
        foreach ($keys as $k) {
            $this->get(route('help.show', $k))->assertOk();
            $this->get(route('help.show', $k), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        }
        $this->get(route('help.show', 'assets'))->assertSee('Nama aset')->assertSee('Konsep');

        $d = app(ExecutiveDashboard::class)->build(true);
        $this->assertNotEmpty($d['kpi']);
        foreach ($d['charts'] as $c) {
            $this->assertNotEmpty($c['id']);
        }
        $this->get('/admin/work-orders?filters[dibuat][from]=2026-01-01&filters[dibuat][until]=2026-12-31')->assertOk();
    }

    public function test_help_requires_login(): void
    {
        $this->get(route('help.show', 'assets'))->assertRedirect();
    }

    public function test_quick_upload_single_and_per_file(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin);
        $type = DocumentType::firstOrFail();
        $asset = Asset::query()->firstOr(fn () => null);
        $ids = $asset ? [$asset->id] : [];
        $svc = app(DocumentService::class);

        $files = [];
        foreach (['izin_genset.pdf', 'slo-panel.pdf'] as $n) {
            Storage::disk('local')->put('documents/'.$n, 'isi '.$n);
            $files['documents/'.$n] = $n;
        }
        $one = $svc->quickUpload($ids, $type->id, $files, false, ['no_expiry' => true], $admin);
        $this->assertCount(1, $one);
        $this->assertSame(2, $one[0]->files()->count());

        $many = $svc->quickUpload($ids, $type->id, $files, true, ['no_expiry' => true], $admin);
        $this->assertCount(2, $many);
        $this->assertSame(['izin genset', 'slo panel'], collect($many)->pluck('title')->all());

        $before = AssetDocument::count();
        Livewire::test(ListAssetDocuments::class)
            ->callAction('unggahCepat', ['document_type_id' => $type->id, 'asset_ids' => $ids, 'per_file' => true, 'no_expiry' => true,
                'files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')]])
            ->assertHasNoActionErrors();
        $this->assertSame($before + 2, AssetDocument::count());
    }
}
