<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** assetcare:complete-room-assets hanya mengisi kolom kosong dari template/kategori dan tidak menimpa isian. */
class CompleteRoomAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_fills_blanks_without_overwriting(): void
    {
        $room = Location::where('location_type', 'room')->firstOrFail();
        $cat = AssetCategory::whereNotNull('default_pm_interval_days')->firstOrFail();
        $blank = Asset::create(['name' => 'Uji kosong', 'category_id' => $cat->id, 'location_id' => $room->id, 'property_id' => $room->property_id]);
        $kept = Asset::create(['name' => 'Uji terisi', 'category_id' => $cat->id, 'location_id' => $room->id, 'property_id' => $room->property_id,
            'pm_interval_days' => 7, 'criticality' => 'high']);
        $blank->forceFill(['pm_interval_days' => null, 'criticality' => null])->saveQuietly();

        $this->artisan('assetcare:complete-room-assets', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($blank->fresh()->pm_interval_days);

        $this->artisan('assetcare:complete-room-assets')->assertSuccessful();
        $this->assertSame((int) $cat->default_pm_interval_days, (int) $blank->fresh()->pm_interval_days);
        $this->assertSame($cat->default_criticality, $blank->fresh()->criticality);
        $this->assertSame(7, (int) $kept->fresh()->pm_interval_days);
        $this->assertSame('high', $kept->fresh()->criticality);
    }
}
