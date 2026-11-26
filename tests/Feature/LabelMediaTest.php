<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\LabelService;
use App\Services\WorkflowException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Label Tom & Jerry No.103 (64 × 32 mm, 3 × 4 = 12 per lembar) dengan kalibrasi posisi & lembar uji. */
class LabelMediaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_tj103_profile_calibration_and_test_sheet(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $p = LabelService::profile('tj-103');
        $this->assertSame([64, 32, 3, 4], [$p['label_w'], $p['label_h'], $p['cols'], $p['rows']]);
        $this->assertSame(12, app(LabelService::class)->layout(23, 'tj-103')['per_sheet']);
        $this->assertSame(2, app(LabelService::class)->layout(23, 'tj-103')['sheets']);
        $this->assertLessThanOrEqual(210, $p['margin_left'] * 2 + 3 * 64 + 2 * $p['gap_x']);

        LabelService::saveCalibration('tj-103', ['margin_left' => 8.5, 'margin_top' => 15, 'gap_x' => 3, 'gap_y' => 2, 'cols' => 3, 'rows' => 4], $admin);
        AppSetting::flush();
        $p = LabelService::profile('tj-103');
        $this->assertSame([8.5, 15.0, 3.0, 2.0], [$p['margin_left'], $p['margin_top'], $p['gap_x'], $p['gap_y']]);

        $this->expectException(WorkflowException::class);
        LabelService::saveCalibration('tj-103', ['margin_left' => 100, 'cols' => 2], $admin); // 100 + 2×64 > 210
    }

    public function test_test_sheet_pdf_and_batch_on_tj103(): void
    {
        User::query()->update(['must_change_password' => false]);
        $u = User::where('email', 'eng.admin@grandpasundanhotel.com')->firstOrFail();
        $this->actingAs($u);
        $this->get(route('labels.test', 'tj-103'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $labels = app(LabelService::class)->select(['include_unverified' => true])->take(12);
        $batch = app(LabelService::class)->createBatch($labels, 'tj-103', 1, $u);
        $this->assertSame((int) ceil($labels->count() / 12), $batch->sheet_count);
        $this->get(route('labels.pdf', $batch))->assertOk();
        \Livewire\Livewire::test(\App\Filament\Pages\CetakLabel::class)->assertSet('profile', 'tj-103')
            ->set('cal.margin_left', 7)->call('saveCal')->assertSet('cal.margin_left', 7.0);
    }

    public function test_room_and_category_lists_follow_selected_location(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $this->actingAs($admin);
        $asset = \App\Models\Asset::whereNotNull('location_id')->firstOrFail();
        $room = \App\Models\Location::findOrFail($asset->location_id);
        $floor = \App\Models\Location::findOrFail($room->parent_id);
        $otherRoom = \App\Models\Location::whereIn('location_type', ['room', 'venue', 'area'])->where('is_active', true)
            ->where('sort_key', 'not like', $floor->sort_key.'/%')->firstOrFail();
        $unused = \App\Models\AssetCategory::create(['code' => 'ZZTEST', 'name' => 'Kategori kosong uji', 'is_active' => true]);

        $lw = \Livewire\Livewire::test(\App\Filament\Pages\CetakLabel::class)
            ->set('roomIds', [(string) $otherRoom->id])->set('categoryIds', [(string) $unused->id])
            ->set('floorId', $floor->id);
        $rooms = $lw->instance()->rooms->pluck('id');
        $this->assertTrue($rooms->contains($room->id));
        $this->assertFalse($rooms->contains($otherRoom->id));
        $this->assertSame([], $lw->get('roomIds'), 'kamar di luar lantai dibuang');
        $this->assertSame([], $lw->get('categoryIds'), 'kategori yang tidak ada di lantai dibuang');
        $cats = $lw->instance()->categories;
        $this->assertTrue($cats->pluck('id')->contains($asset->category_id));
        $this->assertFalse($cats->pluck('id')->contains($unused->id));
        $lw->assertSee('Tampilkan semua');

        $lw->set('roomIds', [(string) $room->id]);
        $expected = \App\Models\Asset::where('location_id', $room->id)->distinct()->pluck('category_id')->sort()->values()->all();
        $this->assertEqualsCanonicalizing($expected, $lw->instance()->categories->pluck('id')->filter(fn ($id) => in_array($id, $expected))->values()->all());

        $lw->set('allRooms', true)->set('allCategories', true);
        $this->assertTrue($lw->instance()->rooms->pluck('id')->contains($otherRoom->id));
        $this->assertTrue($lw->instance()->categories->pluck('id')->contains($unused->id));
    }
}
