<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\RoomAssetTemplate;
use App\Models\RoomException;
use App\Models\RoomType;
use App\Models\User;
use App\Support\CurrentProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Template kamar dari workbook: tipe, kamar, template published, pengecualian, generate dengan pencocokan aset lama. */
class ImportRoomTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function workbook(): string
    {
        $file = sys_get_temp_dir().'/ac-kamar-'.uniqid().'.xlsx';
        $w = new Writer;
        $w->openToFile($file);
        $sheets = [
            'Tipe kamar' => [['Kode tipe', 'Nama tipe', 'Konfigurasi ranjang', 'Gedung', 'Jumlah kamar', 'Catatan'], ['TT-STD', 'Uji Standar', 'King', 'TT', 2, '']],
            'Kamar' => [['Kode lokasi', 'No. kamar', 'Gedung', 'Kode lantai', 'Kode tipe'], ['TT-01-101', 101, 'TT', 'TT-01', 'TT-STD'], ['TT-01-102', 102, 'TT', 'TT-01', 'TT-STD']],
            'Template' => [['Kode tipe', 'Kunci item', 'Nama standar', 'Kode kategori', 'Mode', 'Satuan', 'Jumlah standar', 'Departemen', 'Area'],
                ['TT-STD', 'tv', 'Televisi LED 32 inci', 'TV', 'individual', 'unit', 1, 'ENG', 'Kamar tidur'],
                ['TT-STD', 'nakas', 'Nakas', 'FURN', 'individual', 'unit', 2, 'HK', 'Kamar tidur']],
            'Pengecualian' => [['Kode lokasi', 'Kunci item', 'Nama item', 'Jenis', 'Jumlah aktual', 'Jumlah standar', 'Kode kategori', 'Alasan'],
                ['TT-01-102', 'nakas', 'Nakas', 'quantity', 1, 2, 'FURN', 'Jumlah aktual 1'],
                ['TT-01-102', 'safety-box', 'Safety box', 'added', 1, 0, 'SAFE', 'Tambahan']],
        ];
        $first = true;
        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $w->getCurrentSheet() : $w->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $first = false;
            foreach ($rows as $r) {
                $w->addRow(Row::fromValues($r));
            }
        }
        $w->close();

        return $file;
    }

    public function test_import_templates_exceptions_and_generate_with_matching(): void
    {
        $p = app(CurrentProperty::class)->get() ?? \App\Models\Property::firstOrFail();
        app(CurrentProperty::class)->set($p);
        $bld = Location::create(['property_id' => $p->id, 'code' => 'TT', 'name' => 'Gedung uji', 'location_type' => 'building', 'is_active' => true]);
        $fl = Location::create(['property_id' => $p->id, 'parent_id' => $bld->id, 'code' => 'TT-01', 'name' => 'Lantai 1', 'location_type' => 'floor', 'is_active' => true]);
        $r101 = Location::create(['property_id' => $p->id, 'parent_id' => $fl->id, 'code' => 'TT-01-101', 'name' => 'Kamar 101', 'location_type' => 'room', 'is_active' => true]);
        // TV data awal di kamar 101 → harus dicocokkan, bukan dibuat ulang
        $tv = Asset::create(['property_id' => $p->id, 'name' => 'TV kamar 101', 'category_id' => AssetCategory::where('code', 'TV')->value('id'), 'location_id' => $r101->id,
            'lifecycle_status' => 'draft', 'verification_status' => 'unverified', 'operational_status' => 'serviceable', 'ownership' => 'milik']);
        $file = $this->workbook();

        $this->artisan('assetcare:import-room-templates', ['file' => $file, '--dry-run' => true])->assertSuccessful();
        $this->assertFalse(RoomType::where('code', 'TT-STD')->exists());

        $this->artisan('assetcare:import-room-templates', ['file' => $file, '--generate' => true])->assertSuccessful();
        $type = RoomType::where('code', 'TT-STD')->firstOrFail();
        $this->assertSame($type->id, $r101->fresh()->room_type_id);
        $r102 = Location::where('code', 'TT-01-102')->firstOrFail();
        $this->assertSame('Gedung uji › Lantai 1 › Kamar 102', $r102->full_path);
        $tpl = RoomAssetTemplate::where('code', 'TPL-TT-STD')->where('status', 'published')->firstOrFail();
        $this->assertSame(3, $tpl->unitCount());
        $this->assertSame(2, RoomException::where('room_id', $r102->id)->count());

        // 101: TV lama dicocokkan + 2 nakas baru; 102: TV + 1 nakas + 1 safety box
        $this->assertSame(1, Asset::where('location_id', $r101->id)->where('category_id', $tv->category_id)->count());
        $this->assertTrue(\App\Models\SlotAssignment::where('asset_id', $tv->id)->whereNotNull('active_asset_key')->exists());
        $this->assertSame(3, Asset::where('location_id', $r101->id)->count());
        $this->assertSame(3, Asset::where('location_id', $r102->id)->count());
        $this->assertSame(1, Asset::where('location_id', $r102->id)->where('category_id', AssetCategory::where('code', 'SAFE')->value('id'))->count());
        $this->assertNotNull(Asset::where('location_id', $r102->id)->first()->activeLabel);

        // aman diulang
        $n = Asset::count();
        $this->artisan('assetcare:import-room-templates', ['file' => $file, '--generate' => true])->expectsOutputToContain('sudah lengkap')->assertSuccessful();
        $this->assertSame($n, Asset::count());
        $this->assertSame(1, RoomAssetTemplate::where('code', 'TPL-TT-STD')->count());

        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Resources\RoomAssetTemplates\Pages\ListRoomAssetTemplates::class)
            ->mountAction(\Filament\Actions\Testing\TestAction::make('isi')->table($tpl))
            ->assertMountedActionModalSee('Nakas')->assertMountedActionModalSee('2 kamar');
    }
}
