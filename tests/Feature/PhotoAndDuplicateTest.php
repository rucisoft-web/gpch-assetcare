<?php

namespace Tests\Feature;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Models\Asset;
use App\Models\AssetLabel;
use App\Models\Attachment;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AssetDuplicateService;
use App\Services\WorkflowException;
use App\Support\ImageCompressor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Foto apa pun → JPEG < 500 KB; entri aset dobel dihapus dengan menggabungkannya ke aset yang benar. */
class PhotoAndDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function noisyPng(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 40000; $i++) {
            imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    public function test_any_image_becomes_jpeg_under_500kb(): void
    {
        $png = $this->noisyPng(4000, 3000);
        $jpg = ImageCompressor::toJpeg($png);
        $this->assertNotNull($jpg);
        $this->assertStringStartsWith("\xFF\xD8", $jpg);
        $this->assertLessThanOrEqual(500 * 1024, strlen($jpg));
        [$w, $h] = getimagesizefromstring($jpg);
        $this->assertLessThanOrEqual(1600, max($w, $h));
        $this->assertNull(ImageCompressor::toJpeg('%PDF-1.4 bukan gambar'));
    }

    public function test_field_photo_is_stored_as_small_jpeg(): void
    {
        Storage::fake('local');
        $p = Property::firstOrFail();
        $u = User::create(['name' => 'Petugas Foto', 'email' => 'foto@example.test', 'password' => Hash::make('Rahasia-2026x'),
            'is_active' => true, 'must_change_password' => false, 'property_id' => $p->id]);
        $u->roles()->sync([Role::where('slug', 'field-officer')->value('id')]);
        $asset = Asset::whereNotNull('location_id')->firstOrFail();
        $tmp = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($tmp, $this->noisyPng(3000, 2000));
        $file = new UploadedFile($tmp, 'foto.png', 'image/png', null, true);
        $this->actingAs($u)->post(route('field.identify', $asset), ['found' => '1', 'condition_grade' => 'A', 'photos' => [$file]])->assertRedirect();
        $att = Attachment::where('attachable_id', $asset->id)->where('kind', 'photo')->latest('id')->firstOrFail();
        $this->assertSame('image/jpeg', $att->mime);
        $this->assertLessThanOrEqual(500 * 1024, $att->size);
    }

    public function test_duplicate_entry_is_merged_and_deleted(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $keep = Asset::whereNotNull('location_id')->whereHas('activeLabel')->firstOrFail();
        $keep->forceFill(['brand' => null])->saveQuietly();
        $dup = Asset::create(['name' => $keep->name, 'category_id' => $keep->category_id, 'location_id' => $keep->location_id,
            'property_id' => $keep->property_id, 'brand' => 'Merek Dobel']);
        $dupLabel = $dup->fresh()->activeLabel;
        Attachment::create(['attachable_type' => $dup->getMorphClass(), 'attachable_id' => $dup->id, 'kind' => 'photo', 'disk' => 'local',
            'path' => 'x.jpg', 'original_name' => 'x.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'checksum' => 'x', 'visibility' => 'internal', 'uploaded_by' => $admin->id]);

        $field = User::whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', ['super-admin', 'asset-controller']))->where('is_active', true)->first();
        if ($field && ! $field->hasPermission('asset.delete')) {
            try {
                app(AssetDuplicateService::class)->merge($dup, $keep, 'uji', $field);
                $this->fail('harus ditolak');
            } catch (WorkflowException $e) {
                $this->assertSame(403, $e->getCode());
            }
        }

        $kept = app(AssetDuplicateService::class)->merge($dup, $keep, 'Diinput dua kali', $admin);
        $this->assertNull(Asset::find($dup->id));
        $this->assertSame('Merek Dobel', $kept->brand);
        $this->assertStringContainsString('Entri dobel', $kept->notes);
        $this->assertSame(1, Attachment::where('attachable_id', $keep->id)->where('path', 'x.jpg')->count());
        $this->assertSame(1, AssetLabel::where('asset_id', $keep->id)->where('status', 'active')->count());
        if ($dupLabel) {
            $this->assertSame('revoked', $dupLabel->fresh()->status);
            $this->assertSame($keep->id, (int) $dupLabel->fresh()->asset_id);
        }

        // entri yang sudah punya WO tidak boleh dihapus
        $wo = WorkOrder::query()->whereNotNull('asset_id')->first();
        if ($wo) {
            $this->expectException(WorkflowException::class);
            app(AssetDuplicateService::class)->merge(Asset::findOrFail($wo->asset_id), $keep->is(Asset::find($wo->asset_id)) ? Asset::whereKeyNot($keep->id)->firstOrFail() : $keep, 'uji', $admin);
        }
    }

    public function test_web_upload_is_stored_as_small_jpeg(): void
    {
        Storage::fake('local');
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $this->actingAs($admin);
        $asset = Asset::firstOrFail();
        $tmp = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($tmp, $this->noisyPng(3000, 2000));
        $file = UploadedFile::fake()->createWithContent('besar.png', file_get_contents($tmp));
        Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->set('data.photo_path', [$file])->call('save')->assertHasNoErrors();
        $path = $asset->fresh()->photo_path;
        $this->assertStringEndsWith('.jpg', $path);
        $raw = Storage::disk('local')->get($path);
        $this->assertStringStartsWith("\xFF\xD8", $raw);
        $this->assertLessThanOrEqual(500 * 1024, strlen($raw));
    }

    public function test_duplicate_action_on_asset_page(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $this->actingAs($admin);
        $keep = Asset::whereNotNull('location_id')->firstOrFail();
        $dup = Asset::create(['name' => $keep->name, 'category_id' => $keep->category_id, 'location_id' => $keep->location_id, 'property_id' => $keep->property_id]);
        Livewire::test(ViewAsset::class, ['record' => $dup->getRouteKey()])
            ->callAction('dobel', ['keep_id' => $keep->id, 'reason' => 'Diinput dua kali'])->assertHasNoActionErrors()
            ->assertRedirect(AssetResource::getUrl('view', ['record' => $keep]));
        $this->assertNull(Asset::find($dup->id));
    }
}
