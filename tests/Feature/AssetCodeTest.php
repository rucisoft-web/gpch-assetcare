<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Services\QrResolver;
use App\Support\AssetCode;
use App\Support\CurrentProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kode aset mencerminkan lokasi & jenis: LOKASI-JENIS-URUT. */
class AssetCodeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_code_reflects_location_and_category_and_follows_moves(): void
    {
        $p = app(CurrentProperty::class)->get() ?? \App\Models\Property::firstOrFail();
        app(CurrentProperty::class)->set($p);
        $b = Location::create(['property_id' => $p->id, 'code' => 'ZZ', 'name' => 'Gedung Z', 'location_type' => 'building', 'is_active' => true]);
        $f = Location::create(['property_id' => $p->id, 'parent_id' => $b->id, 'code' => 'ZZ-08', 'name' => 'Lantai 8', 'location_type' => 'floor', 'is_active' => true]);
        $r819 = Location::create(['property_id' => $p->id, 'parent_id' => $f->id, 'code' => 'ZZ-08-819', 'name' => 'Kamar 819', 'location_type' => 'room', 'is_active' => true]);
        $r820 = Location::create(['property_id' => $p->id, 'parent_id' => $f->id, 'code' => 'ZZ-08-820', 'name' => 'Kamar 820', 'location_type' => 'room', 'is_active' => true]);
        $tv = AssetCategory::where('code', 'TV')->firstOrFail();
        $mk = fn ($loc) => Asset::create(['property_id' => $p->id, 'name' => 'TV', 'category_id' => $tv->id, 'location_id' => $loc->id,
            'lifecycle_status' => 'draft', 'verification_status' => 'unverified', 'operational_status' => 'serviceable', 'ownership' => 'milik']);

        $a = $mk($r819);
        $b2 = $mk($r819);
        $this->assertSame('ZZ-08-819-TV-01', $a->asset_code);
        $this->assertSame('ZZ-08-819-TV-02', $b2->asset_code);

        $a->update(['location_id' => $r820->id]);
        $this->assertSame('ZZ-08-820-TV-01', $a->fresh()->asset_code);
        $this->assertSame('ZZ-08-819-TV-01', $a->fresh()->former_codes);
        // kode lama dan baru sama-sama bisa dibuka
        $this->assertSame($a->id, app(QrResolver::class)->resolve('zz-08-820-tv-01')['asset']->id);
        $this->assertSame($a->id, app(QrResolver::class)->resolve('ZZ-08-819-TV-01')['asset']->id);
        // nomor tidak dipakai ulang
        $this->assertSame('ZZ-08-819-TV-03', $mk($r819)->asset_code);

        $long = new Location(['code' => 'WW-LD-KORIDORLOBBI3']);
        $this->assertSame('WW-LD-KORIDORLOBB3', AssetCode::locationPart($long));
    }

    public function test_recode_command_converts_old_codes(): void
    {
        $a = Asset::whereNotNull('location_id')->whereNotNull('category_id')->firstOrFail();
        \Illuminate\Support\Facades\DB::table('assets')->where('id', $a->id)->update(['asset_code' => 'GPCH-AST-999999']);
        $this->artisan('assetcare:recode-assets')->assertSuccessful();
        $a->refresh();
        $this->assertStringStartsWith(AssetCode::prefix($a->location_id, $a->category_id).'-', $a->asset_code);
        $this->assertStringContainsString('GPCH-AST-999999', (string) $a->former_codes);
        $this->assertSame(0, Asset::where('asset_code', 'like', 'TMP-%')->count());
        $this->assertSame(Asset::count(), Asset::distinct()->count('asset_code'));
    }
}
