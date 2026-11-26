<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetQuantityGroup;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Menu Lapangan (/lapangan) untuk petugas identifikasi aset: terpisah dari panel admin. */
class FieldAppTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function officer(): User
    {
        $p = app(CurrentProperty::class)->get() ?? \App\Models\Property::firstOrFail();
        $u = User::create(['name' => 'Petugas Uji', 'email' => 'petugas.uji@example.test', 'password' => Hash::make('Rahasia-2026x'),
            'is_active' => true, 'must_change_password' => false, 'property_id' => $p->id]);
        $u->roles()->sync([Role::where('slug', 'field-officer')->value('id')]);

        return $u->fresh();
    }

    public function test_field_officer_only_sees_field_menu(): void
    {
        $u = $this->officer();
        $this->assertTrue($u->isFieldOnly());

        $this->post('/lapangan/login', ['email' => $u->email, 'password' => 'Rahasia-2026x'])->assertRedirect(route('field.home'));
        $this->get('/admin')->assertRedirect(route('field.home'));
        $this->get('/admin/assets')->assertRedirect(route('field.home'));

        foreach (['field.home', 'field.scan', 'field.location', 'field.search', 'field.password'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $asset = Asset::whereNotNull('location_id')->firstOrFail();
        $this->get(route('field.location', $asset->location_id))->assertOk()->assertSee($asset->name);
        $this->get(route('field.search', ['q' => $asset->asset_code]))->assertOk()->assertSee($asset->asset_code);
        $this->get(route('field.asset', $asset))->assertOk();
    }

    public function test_admin_keeps_web_panel_and_guest_goes_to_field_login(): void
    {
        $this->get('/lapangan')->assertRedirect(route('field.login'));
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $this->assertFalse($admin->isFieldOnly());
        $this->actingAs($admin);
        $this->get('/admin')->assertOk();
        $this->get('/lapangan')->assertOk();
    }

    public function test_identify_found_asset_and_record_new_and_group(): void
    {
        Storage::fake('local');
        $u = $this->officer();
        $this->actingAs($u);
        $asset = Asset::whereNotNull('location_id')->where('verification_status', '!=', 'verified')->firstOrFail();

        $this->post(route('field.identify', $asset), ['found' => '1'])->assertSessionHasErrors('condition_grade');
        $this->post(route('field.identify', $asset), ['found' => '0'])->assertSessionHasErrors('note');

        $this->post(route('field.identify', $asset), [
            'found' => '1', 'condition_grade' => array_key_first(Asset::CONDITIONS), 'brand' => 'Merek Uji', 'note' => 'Dicek di kamar',
            'photos' => [UploadedFile::fake()->image('a.jpg', 2400, 1800)],
        ])->assertRedirect()->assertSessionHas('ok');
        $asset->refresh();
        $this->assertSame('verified', $asset->verification_status);
        $this->assertSame($u->id, (int) $asset->verified_by);
        $this->assertSame('Merek Uji', $asset->brand);
        $this->assertStringContainsString('Dicek di kamar', $asset->notes);
        $this->assertNotNull($asset->photo_path);
        Storage::disk('local')->assertExists($asset->photo_path);

        $loc = Location::findOrFail($asset->location_id);
        $cat = AssetCategory::where('is_active', true)->firstOrFail();
        $this->post(route('field.new.save', $loc), ['name' => 'Kursi temuan', 'category_id' => $cat->id,
            'condition_grade' => array_key_first(Asset::CONDITIONS), 'operational_status' => array_key_first(Asset::OPERATIONAL)])
            ->assertRedirect(route('field.location', $loc));
        $new = Asset::where('name', 'Kursi temuan')->firstOrFail();
        $this->assertSame('Temuan lapangan', $new->migration_source);
        $this->assertNotNull($new->activeLabel);

        $g = AssetQuantityGroup::create(['property_id' => $loc->property_id, 'location_id' => $loc->id, 'category_id' => $cat->id,
            'name' => 'Handuk', 'expected_qty' => 4, 'status' => 'active']);
        $this->get(route('field.group', $g))->assertOk();
        $this->post(route('field.group.count', $g), ['qty' => 3])->assertSessionHasErrors('reason');
        $this->post(route('field.group.count', $g), ['qty' => 3, 'reason' => 'Satu di laundry'])->assertRedirect();
        $this->assertEquals(3, (float) $g->fresh()->on_hand);
    }

    public function test_install_label_by_scan_then_supervisor_approves(): void
    {
        $u = $this->officer();
        $asset = Asset::whereNotNull('location_id')->where('verification_status', 'unverified')->whereHas('activeLabel')->firstOrFail();
        $room = Location::findOrFail($asset->location_id);
        $other = Asset::whereNotNull('location_id')->where('location_id', '!=', $room->id)->whereHas('activeLabel')->firstOrFail();
        $this->actingAs($u);

        $this->get(route('field.install', $room))->assertOk()->assertSee($asset->name);
        $this->get(route('field.install', $room))->assertSee('Pakai handheld scanner')->assertSee('id="hh-in"', false);
        // label milik ruang lain ditolak
        $this->post(route('field.install.scan', $room), ['kode' => $other->activeLabel->url()])->assertRedirect(route('field.install', $room))->assertSessionHas('err');
        // label benar: tercatat terpasang lalu diarahkan ke form pengecekan
        $this->post(route('field.install.scan', $room), ['kode' => $asset->activeLabel->url()])->assertRedirect(route('field.asset', [$asset, 'pasang' => 1]));
        $this->assertSame('installed', $asset->fresh()->installation_status);
        $this->post(route('field.identify', $asset), ['found' => '1', 'condition_grade' => 'A', 'pasang' => '1'])->assertRedirect(route('field.install', $room));

        // petugas tidak bisa membuka halaman persetujuan
        $this->get(\App\Filament\Pages\PersetujuanLapangan::getUrl())->assertRedirect(route('field.home'));

        // label bermasalah masuk antrean cetak ulang
        $broken = Asset::where('location_id', $room->id)->whereKeyNot($asset->id)->whereHas('activeLabel')->first();
        if ($broken) {
            $this->post(route('field.install.mark', [$room, $broken->activeLabel]), ['status' => 'damaged'])->assertRedirect(route('field.install', $room));
            $this->assertSame('damaged', $broken->fresh()->installation_status);
        }

        // penyetuju (orang lain) mengaktifkan
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $this->flushSession();
        $this->actingAs($admin);
        $this->get(\App\Filament\Pages\PersetujuanLapangan::getUrl())->assertOk();
        \Livewire\Livewire::test(\App\Filament\Pages\PersetujuanLapangan::class)
            ->assertCanSeeTableRecords([$asset])
            ->assertCanNotSeeTableRecords(Asset::where('verification_status', 'unverified')->limit(3)->get())
            ->callTableBulkAction('setujui', [$asset]);
        $this->assertSame('active', $asset->fresh()->lifecycle_status, json_encode($asset->fresh()->missingForActivation()));
    }
}
