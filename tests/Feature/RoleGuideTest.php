<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Help\RoleGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Menu "Panduan per peran": semua peran punya panduan & gambar; petugas lapangan punya panduan di ponsel. */
class RoleGuideTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_every_role_has_a_guide_with_existing_screenshots(): void
    {
        $all = RoleGuide::all();
        foreach (Role::pluck('slug') as $slug) {
            $this->assertArrayHasKey($slug, $all, "Peran $slug belum punya panduan");
            $this->assertNotEmpty($all[$slug]['tugas']);
            foreach ($all[$slug]['tugas'] as [$judul, $langkah, $img]) {
                $this->assertFileExists(public_path("help/peran/$img.jpg"), "$slug: $img");
            }
        }
    }

    public function test_guide_pages_render(): void
    {
        User::query()->update(['must_change_password' => false]);
        $u = User::whereHas('roles', fn ($q) => $q->where('slug', 'technician'))->firstOrFail();
        $this->actingAs($u);
        $this->get('/admin/panduan-peran')->assertOk()->assertSee('Teknisi')->assertSee('Tugas saya')->assertSee('help/peran/m-tek-wo.jpg', false);
        $this->get('/admin/panduan-peran?peran=field-officer')->assertOk()->assertSee('Pasang label QR');
        $this->get(route('help.show', 'pages.persetujuan-lapangan'))->assertOk()->assertSee('Setujui');

        $this->flushSession();
        $p = User::create(['name' => 'P', 'email' => 'p@example.test', 'password' => bcrypt('Rahasia-2026x'), 'is_active' => true, 'must_change_password' => false, 'property_id' => $u->property_id]);
        $p->roles()->sync([Role::where('slug', 'field-officer')->value('id')]);
        $this->actingAs($p);
        $this->get(route('field.guide'))->assertOk()->assertSee('Tempel label lalu scan');
        $this->get(route('field.password'))->assertOk()->assertSee(route('field.guide'), false);
    }
}
