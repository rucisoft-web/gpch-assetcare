<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_scan_page_and_report(): void
    {
        $company = $this->company('Hotel QR');
        $this->useCompany($company);
        $asset = Asset::create(['name' => 'AC Kamar 101', 'asset_category_id' => AssetCategory::first()->id, 'location_id' => Location::create(['name' => 'Kamar 101'])->id]);
        app(CurrentCompany::class)->set(null);

        $this->get('/qr/'.$asset->qr_token)->assertOk()->assertSee('AC Kamar 101')->assertSee('Hotel QR');
        $this->get('/qr/'.str_repeat('a', 40))->assertNotFound();

        $this->post('/qr/'.$asset->qr_token.'/lapor', [
            'request_type' => 'corrective', 'priority' => 'high', 'description' => 'AC tidak dingin', 'reporter_name' => 'Tamu',
        ])->assertOk()->assertSee('Laporan terkirim');

        $request = MaintenanceRequest::withoutGlobalScopes()->first();
        $this->assertTrue($request->via_qr);
        $this->assertSame($company->id, $request->company_id);
        $this->assertNull($request->reporter_id);
        $this->assertSame('[Laporan QR] AC tidak dingin', $request->description);

        // Perangkap bot.
        $this->post('/qr/'.$asset->qr_token.'/lapor', ['request_type' => 'corrective', 'priority' => 'low', 'description' => 'spam spam', 'website' => 'x'])
            ->assertSessionHasErrors('website');

        // Token baru membatalkan token lama.
        $old = $asset->qr_token;
        $asset->regenerateQr();
        $this->get('/qr/'.$old)->assertNotFound();
    }

    public function test_labels_only_for_members(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $this->useCompany($a);
        $asset = Asset::create(['name' => 'Genset', 'asset_category_id' => AssetCategory::first()->id, 'location_id' => Location::create(['name' => 'Plant'])->id]);
        app(CurrentCompany::class)->set(null);

        $this->actingAs($this->member($a))->get('/label-qr?ids='.$asset->id)->assertOk()->assertSee('Genset')->assertSee('<svg', false);
        $this->actingAs($this->member($b))->get('/label-qr?ids='.$asset->id)->assertNotFound();
    }

    public function test_labels_per_location_include_sub_locations(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $this->useCompany($a);
        $cat = AssetCategory::first()->id;
        $floor = Location::create(['name' => 'Lantai 4']);
        $room = Location::create(['name' => 'Kamar 401', 'parent_id' => $floor->id]);
        $other = Location::create(['name' => 'Lobby']);
        Asset::create(['name' => 'AC Kamar 401', 'asset_category_id' => $cat, 'location_id' => $room->id]);
        Asset::create(['name' => 'Sofa Lobby', 'asset_category_id' => $cat, 'location_id' => $other->id]);
        app(CurrentCompany::class)->set(null);

        $this->actingAs($this->member($a))->get('/label-qr?location='.$floor->id)
            ->assertOk()->assertSee('AC Kamar 401')->assertDontSee('Sofa Lobby');
        $this->actingAs($this->member($b))->get('/label-qr?location='.$floor->id)->assertNotFound();
    }
}
