<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Nama aplikasi generik "Asset Care" + identitas perusahaan yang dapat diatur. */
class BrandTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_company_identity_can_be_changed_and_is_used_in_panel(): void
    {
        AppSetting::flush();
        $this->assertSame('Asset Care · GPCH', Brand::panelName());
        $this->get('/brand/logo')->assertOk();
        $this->get('/admin/login')->assertOk()->assertSee('Asset Care · GPCH', false);

        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        $this->actingAs($admin);
        $this->get('/admin/identitas-perusahaan')->assertOk();

        \Livewire\Livewire::test(\App\Filament\Pages\IdentitasPerusahaan::class)
            ->fillForm(['company_name' => 'Hotel Contoh Nusantara', 'company_short' => 'HCN', 'legal_name' => 'PT Contoh Nusantara',
                'address' => 'Jl. Contoh 1', 'city' => 'Jakarta', 'primary_color' => '#7A1F2B', 'show_ornament' => false,
                'logo_path' => UploadedFile::fake()->image('logo.png', 240, 80)])
            ->call('save')->assertHasNoFormErrors();

        AppSetting::flush();
        $this->assertSame('Asset Care · HCN', Brand::panelName());
        $this->assertSame('#7A1F2B', Brand::color());
        $this->assertFalse(Brand::showOrnament());
        $this->assertStringStartsWith('branding/', Brand::get('logo_path'));
        $this->assertTrue(Storage::disk('local')->exists(Brand::get('logo_path')));
        $this->get('/admin')->assertOk()->assertSee('Asset Care · HCN', false)->assertSee('#7A1F2B', false)->assertSee('Hotel Contoh Nusantara');
        $this->get('/brand/logo')->assertOk();
        $this->get('/brand/../.env')->assertNotFound();

        // pengguna tanpa izin pengaturan tidak bisa membuka halaman
        $tech = User::where('email', 'teknisi1@grandpasundanhotel.com')->firstOrFail();
        $this->actingAs($tech);
        $this->assertFalse(\App\Filament\Pages\IdentitasPerusahaan::canAccess());
    }
}
