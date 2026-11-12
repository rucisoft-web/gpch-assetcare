<?php

namespace Tests\Feature;

use App\Filament\App\Resources\Assets\Pages\ListAssets;
use App\Filament\App\Resources\AssetTemplates\Pages\ListAssetTemplates;
use App\Filament\App\Resources\Locations\Pages\ManageLocations;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTemplate;
use App\Models\Location;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Aksi jenis ruang di panel: bangkitkan per jenis ruang, terapkan & duplikasi templat, tetapkan jenis ruang massal. */
class RoomTypeActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_type_actions_in_panel(): void
    {
        $company = $this->company();
        $this->useCompany($company);
        $cat = AssetCategory::first();
        $t = AssetTemplate::create(['name' => 'Kamar Deluxe']);
        $t->lines()->create(['name' => 'AC', 'asset_category_id' => $cat->id, 'quantity' => 1]);
        $f4 = Location::create(['name' => 'Lantai 4']);
        $f5 = Location::create(['name' => 'Lantai 5']);
        $a = Location::create(['name' => 'Kamar 401', 'parent_id' => $f4->id, 'asset_template_id' => $t->id]);
        $b = Location::create(['name' => 'Kamar 501', 'parent_id' => $f5->id, 'asset_template_id' => $t->id]);

        $user = User::factory()->create();
        $user->memberships()->create(['company_id' => $company->id, 'role' => 'manajer', 'is_active' => true]);
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($company);

        Livewire::test(ListAssets::class)
            ->callAction('generate_from_room_types', data: ['template_ids' => [$t->id], 'parent_id' => $f4->id, 'skip_existing' => true])
            ->assertHasNoActionErrors();
        $this->assertSame(1, Asset::where('location_id', $a->id)->count());
        $this->assertSame(0, Asset::where('location_id', $b->id)->count());

        Livewire::test(ListAssetTemplates::class)
            ->callTableAction('apply', $t, data: ['location_ids' => [$b->id], 'skip_existing' => true])
            ->assertHasNoTableActionErrors()
            ->callTableAction('duplicate', $t, data: ['name' => 'Kamar Deluxe Twin'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(1, Asset::where('location_id', $b->id)->count());
        $this->assertSame(1, AssetTemplate::where('name', 'Kamar Deluxe Twin')->first()->lines()->count());

        Livewire::test(ManageLocations::class)
            ->callTableBulkAction('set_template', [$f4, $f5], data: ['asset_template_id' => $t->id])
            ->assertHasNoTableBulkActionErrors();
        $this->assertSame($t->id, $f5->fresh()->asset_template_id);
    }
}
