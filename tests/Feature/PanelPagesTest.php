<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\AssetSurvey;
use App\Models\AssetTemplate;
use App\Models\Company;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\CurrentCompany;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Semua halaman panel terbuka tanpa galat untuk setiap peran, memakai data contoh GPCH. */
class PanelPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        app(CurrentCompany::class)->set(null);
    }

    public function test_every_page_renders_for_each_role(): void
    {
        $company = Company::where('slug', 'gpch')->first();
        $base = '/app/gpch';
        $ids = fn ($model) => $model::withoutGlobalScopes()->where('company_id', $company->id)->value('id');

        $common = [
            '', '/assets', '/assets/'.$ids(Asset::class), '/inspections', '/maintenance-requests',
            '/maintenance-requests/'.$ids(MaintenanceRequest::class), '/work-orders', '/work-orders/'.$ids(WorkOrder::class),
            '/preventive-plans', '/asset-risks', '/capex-proposals', '/asset-surveys', '/asset-surveys/'.$ids(AssetSurvey::class), '/verifikasi-aset', '/mode-lapangan',
        ];
        $managerOnly = ['/departments', '/locations', '/asset-categories', '/asset-templates', '/asset-templates/'.$ids(AssetTemplate::class).'/edit', '/audit-logs'];
        $operate = ['/assets/create', '/assets/'.$ids(Asset::class).'/edit', '/inspections/create', '/work-orders/create'];

        foreach (['manajer@gpch.demo' => Role::Manajer, 'teknisi@gpch.demo' => Role::Staf, 'gm@gpch.demo' => Role::Eksekutif] as $email => $role) {
            $user = User::where('email', $email)->first();
            $this->flushSession();
            $this->app['auth']->forgetGuards();
            $this->actingAs($user);

            $check = fn (string $path, int $status) => $this->assertSame($status, $this->get($base.$path)->status(), "{$email} {$path}");

            foreach ($common as $path) {
                $check($path, 200);
            }

            foreach ($managerOnly as $path) {
                $check($path, $role === Role::Manajer ? 200 : 403);
            }

            foreach ($operate as $path) {
                $check($path, $role === Role::Eksekutif ? 403 : 200);
            }
        }
    }

    public function test_dashboard_shows_kpis_and_drilldown_links(): void
    {
        $this->actingAs(User::where('email', 'gm@gpch.demo')->first())
            ->get('/app/gpch')
            ->assertOk()
            ->assertSee('Asset Care Index')
            ->assertSee('Lift Penumpang #3')
            ->assertSee('tab=kritis', false);
    }

    public function test_non_member_cannot_open_company(): void
    {
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get('/app/gpch')->assertNotFound();
    }

    public function test_platform_panel_for_admin_only(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->get('/platform/companies')->assertOk()->assertSee('Grand Pasundan');
        $this->actingAs($admin)->get('/platform/users')->assertOk();

        $this->actingAs(User::where('email', 'manajer@gpch.demo')->first())->get('/platform/companies')->assertForbidden();
    }
}
