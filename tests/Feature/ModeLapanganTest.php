<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VerificationStatus;
use App\Filament\App\Pages\ModeLapangan;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Inspection;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Support\CurrentCompany;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Mode Lapangan: pindai, verifikasi, inspeksi, lapor, tambah aset; dan ukuran label. */
class ModeLapanganTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_mark_inspect_report_and_add_asset(): void
    {
        $company = $this->company('Hotel Lapangan');
        $this->useCompany($company);
        $room = Location::create(['name' => 'Kamar 401']);
        $asset = Asset::create(['name' => 'AC Kamar 401', 'asset_category_id' => AssetCategory::first()->id, 'location_id' => $room->id]);

        $this->actingAs($this->member($company, Role::Staf));
        Filament::setCurrentPanel('app');
        Filament::setTenant($company);

        // Isi QR berupa URL, token saja, dan kode aset.
        foreach ([url('/qr/'.$asset->qr_token), $asset->qr_token, $asset->code] as $input) {
            Livewire::test(ModeLapangan::class)->set('scan', $input)->call('submitScan')
                ->assertSet('assetId', $asset->id)->assertSet('locationId', $room->id)->assertSee('AC Kamar 401');
        }

        Livewire::test(ModeLapangan::class)->set('scan', 'TIDAK-ADA')->call('submitScan')->assertSet('assetId', null);

        $page = Livewire::test(ModeLapangan::class, ['assetId' => $asset->id])
            ->call('mark', $asset->id, 'verified');
        $this->assertSame(VerificationStatus::Verified, $asset->fresh()->verification_status);

        $page->call('mark', $asset->id, 'additional')->assertForbidden();

        Livewire::test(ModeLapangan::class)->set('assetId', $asset->id)
            ->callAction('inspect', data: ['result' => 'pass', 'condition_score' => 65, 'finding' => 'Filter kotor', 'update_condition' => true])
            ->assertHasNoActionErrors()
            ->callAction('report', data: ['request_type' => 'corrective', 'priority' => 'high', 'description' => 'AC bocor air'])
            ->assertHasNoActionErrors();
        $this->assertSame(1, Inspection::where('asset_id', $asset->id)->count());
        $this->assertEquals(65, $asset->fresh()->physical_condition);
        $this->assertSame(1, MaintenanceRequest::where('asset_id', $asset->id)->count());

        Livewire::test(ModeLapangan::class)->set('locationId', $room->id)
            ->callAction('addAsset', data: ['name' => 'Kulkas Mini', 'asset_category_id' => AssetCategory::first()->id])
            ->assertHasNoActionErrors();
        $new = Asset::where('name', 'Kulkas Mini')->first();
        $this->assertSame($room->id, $new->location_id);
        $this->assertSame(VerificationStatus::Additional, $new->verification_status);
    }

    public function test_executive_is_read_only(): void
    {
        $company = $this->company();
        $this->useCompany($company);
        $asset = Asset::create(['name' => 'Genset', 'asset_category_id' => AssetCategory::first()->id, 'location_id' => Location::create(['name' => 'Plant'])->id]);
        $this->actingAs($this->member($company, Role::Eksekutif));
        Filament::setCurrentPanel('app');
        Filament::setTenant($company);

        Livewire::test(ModeLapangan::class, ['assetId' => $asset->id])
            ->assertActionHidden('inspect')
            ->call('mark', $asset->id, 'verified')->assertForbidden();
    }

    public function test_label_sizes(): void
    {
        $company = $this->company();
        $this->useCompany($company);
        $asset = Asset::create(['name' => 'Lift Tamu', 'asset_category_id' => AssetCategory::first()->id, 'location_id' => Location::create(['name' => 'Lobby'])->id]);
        app(CurrentCompany::class)->set(null);
        $user = $this->member($company);

        $this->actingAs($user)->get('/label-qr?ids='.$asset->id.'&size=50x30')
            ->assertOk()->assertSee('size:50mm 30mm', false)->assertSee('Lift Tamu')->assertCookie('label_size', '50x30');

        $this->actingAs($user)->withCookie('label_size', 'tape18')->get('/label-qr?ids='.$asset->id)
            ->assertOk()->assertSee('size:50mm 18mm', false);

        $this->actingAs($user)->get('/label-qr?ids='.$asset->id.'&size=ngawur')
            ->assertOk()->assertSee('size:A4', false);
    }
}
