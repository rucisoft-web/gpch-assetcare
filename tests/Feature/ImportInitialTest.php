<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetQuantityGroup;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Impor data awal dari workbook: lokasi, aset draft, kelompok jumlah, foto; aman diulang. */
class ImportInitialTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function workbook(string $dir): string
    {
        $file = $dir.'/awal.xlsx';
        $w = new Writer;
        $w->openToFile($file);
        $w->getCurrentSheet()->setName('Lokasi');
        foreach ([['Kode lokasi', 'Kode induk', 'Nama', 'Jenis', 'Label lantai', 'No. kamar'],
            ['TST', '', 'Gedung uji', 'building', '', ''],
            ['TST-B', 'TST', 'Basement', 'floor', 'B', ''],
            ['TST-B-ENG', 'TST-B', 'Engineering', 'area', '', '']] as $r) {
            $w->addRow(Row::fromValues($r));
        }
        $w->addNewSheetAndMakeItCurrent()->setName('Kategori');
        $w->addRow(Row::fromValues(['Kode', 'Nama', 'Mode pencatatan', 'Interval PM default (hari)']));
        $w->addRow(Row::fromValues(['UJI-X', 'Kategori uji', 'individual', 45]));
        $w->addNewSheetAndMakeItCurrent()->setName('Aset');
        $h = ['Impor', 'Mode', 'Kode kategori', 'Nama aset', 'Merek', 'Model / tipe', 'No. seri', 'Spesifikasi', 'Jumlah', 'Satuan', 'Jumlah standar',
            'Kode lokasi', 'Departemen', 'Unit penanggung jawab', 'Kondisi', 'Status operasional', 'Kritikalitas', 'Interval PM (hari)', 'Berlaku s.d.', 'Harga (Rp)',
            'Tgl pembelian', 'Catatan / remark terbaru', 'Remark lama', 'Foto', 'Sumber', 'Perlu dicek', 'Diasumsikan', 'Alasan tidak diimpor', 'Kunci'];
        $w->addRow(Row::fromValues($h));
        $rows = [
            ['Ya', 'Individual', 'PUMP', 'Pompa transfer #1', 'Grundfos', 'CR10', 'SN-1', '5.5 kW', 1, 'unit', null, 'TST-B-ENG', 'ENG', 'Engineering', 'B — kerusakan ringan', 'Menurun', 'Tinggi', null, '', 2500000, 'AGUSTUS 2019', 'BERISIK', 'OK', 'p.jpg', 'ENG › pompa › baris 4', 'cek lantai', '', '', 'K-1'],
            ['Ya', 'Individual', 'UJI-X', 'Alat uji', '', '', '', '', 1, 'unit', null, 'TST-B-ENG', 'ENG', 'Engineering', '', '', '', null, '20-06-2027', null, '2023', '', '', '', 'x', '', 'kondisi', '', 'K-2'],
            ['Ya', 'Kelompok', 'LINEN', 'Bath towel', '', '', '', '', 120, 'pcs', 150, 'TST-B-ENG', 'HK', 'Engineering', '', '', '', null, '', null, '', '', '', '', 'x', '', '', '', 'K-3'],
            ['Tidak', 'Individual', 'PC', 'Mouse', '', '', '', '', 1, 'unit', null, 'TST-B-ENG', 'EDP', '', '', '', '', null, '', null, '', '', '', '', 'x', '', '', 'aksesori', 'K-4'],
            ['Ya', 'Individual', 'PUMP', 'Pompa salah lokasi', '', '', '', '', 1, 'unit', null, 'TIDAK-ADA', 'ENG', 'Engineering', '', '', '', null, '', null, '', '', '', '', 'x', '', '', '', 'K-5'],
        ];
        foreach ($rows as $r) {
            $w->addRow(Row::fromValues($r));
        }
        $w->close();

        return $file;
    }

    public function test_import_creates_locations_assets_groups_and_photos_idempotently(): void
    {
        Storage::fake('local');
        $dir = sys_get_temp_dir().'/ac-import-'.uniqid();
        mkdir($dir.'/foto', 0777, true);
        $img = imagecreatetruecolor(20, 20);
        imagejpeg($img, $dir.'/foto/p.jpg');
        $file = $this->workbook($dir);

        $this->artisan('assetcare:import-initial', ['file' => $file, '--photos' => $dir.'/foto', '--dry-run' => true])->assertSuccessful();
        $this->assertFalse(Asset::where('legacy_code', 'K-1')->exists(), 'uji coba tidak menyimpan');

        $this->artisan('assetcare:import-initial', ['file' => $file, '--photos' => $dir.'/foto'])
            ->expectsOutputToContain('kode lokasi tidak valid')->assertSuccessful();

        $eng = Location::where('code', 'TST-B-ENG')->firstOrFail();
        $this->assertSame('Gedung uji › Basement › Engineering', $eng->full_path);
        $a = Asset::where('legacy_code', 'K-1')->firstOrFail();
        $this->assertSame(['draft', 'unverified', 'B', 'degraded', 'high', 'SN-1', '2019-08-01'],
            [$a->lifecycle_status, $a->verification_status, $a->condition_grade, $a->operational_status, $a->criticality, $a->serial_number, $a->purchase_date->toDateString()]);
        $this->assertSame($eng->id, $a->location_id);
        $this->assertStringContainsString('BERISIK', $a->notes);
        $this->assertEquals(2500000, $a->financial->replacement_estimate);
        $this->assertNotNull($a->activeLabel);
        $this->assertNotNull($a->photo_path);
        Storage::disk('local')->assertExists($a->photo_path);
        $this->assertSame(1, $a->attachments()->where('kind', 'photo')->count());

        $b = Asset::where('legacy_code', 'K-2')->firstOrFail();
        $this->assertSame(45, $b->pm_interval_days, 'kategori baru dibuat dari sheet Kategori');
        $this->assertStringContainsString('Berlaku s.d.: 20-06-2027', $b->notes);
        $this->assertStringContainsString('Pembelian: 2023', $b->notes);
        $this->assertStringContainsString('Diasumsikan saat data awal', $b->notes);
        $this->assertSame('Engineering', $a->custodian_unit);
        $this->assertFalse(Asset::where('legacy_code', 'K-4')->exists());
        $g = AssetQuantityGroup::where('name', 'Bath towel')->firstOrFail();
        $this->assertEquals([120, 150], [(float) $g->on_hand, (float) $g->expected_qty]);

        $n = Asset::count();
        $this->artisan('assetcare:import-initial', ['file' => $file, '--photos' => $dir.'/foto'])->assertSuccessful();
        $this->assertSame($n, Asset::count(), 'impor ulang tidak menggandakan');
        $this->assertSame(1, AssetQuantityGroup::where('name', 'Bath towel')->count());

        // label yang hilang (mis. impor SQL) diterbitkan ulang
        \App\Models\AssetLabel::where('asset_id', $a->id)->delete();
        $this->artisan('assetcare:labels-ensure')->assertSuccessful();
        $this->assertNotNull($a->fresh()->activeLabel);
        $this->artisan('assetcare:labels-ensure')->expectsOutputToContain('Label baru: 0')->assertSuccessful();

        // foto utama tampil di kartu aset
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $this->actingAs($admin);
        $this->get('/admin/assets/'.$a->id)->assertOk()->assertSee('class="ac-photo"', false);
        $this->get(route('assets.photo', $a))->assertOk();
        $this->get(route('assets.photo', $b))->assertNotFound();
    }
}
