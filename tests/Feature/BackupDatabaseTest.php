<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupDatabase;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/** Backup database: .sql bertanggal dalam ZIP, dibuat & diunduh dari menu Pengaturan oleh IT. */
class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_it_admin_creates_and_downloads_dated_sql_zip(): void
    {
        Storage::fake('local');
        $p = Property::firstOrFail();
        $it = User::create(['name' => 'Staf IT', 'email' => 'it.uji@example.test', 'password' => Hash::make('Rahasia-2026x'),
            'is_active' => true, 'must_change_password' => false, 'property_id' => $p->id]);
        $it->roles()->sync([Role::where('slug', 'it-admin')->value('id')]);
        $this->actingAs($it->fresh());
        $this->get(BackupDatabase::getUrl())->assertOk()->assertSee('Buat backup sekarang');

        Livewire::test(BackupDatabase::class)->call('create')->assertHasNoErrors();
        $list = app(BackupService::class)->list();
        $this->assertCount(1, $list);
        $name = $list[0]['name'];
        $this->assertMatchesRegularExpression('/^asset-care-gpch_db_\d{4}-\d{2}-\d{2}_\d{6}\.zip$/', $name);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($list[0]['path'])) === true);
        $sql = $zip->getFromName(str_replace('.zip', '.sql', $name));
        $zip->close();
        $this->assertStringContainsString('-- Tanggal backup', $sql);
        $this->assertStringContainsString('INSERT INTO', $sql);
        $this->assertStringContainsString('assets', $sql);

        Livewire::test(BackupDatabase::class)->call('download', $name)->assertFileDownloaded($name);
        Livewire::test(BackupDatabase::class)->call('download', '../../.env')->assertNoFileDownloaded();
        Livewire::test(BackupDatabase::class)->call('remove', $name);
        $this->assertCount(0, app(BackupService::class)->list());
    }

    public function test_other_roles_cannot_open_backup_page(): void
    {
        $p = Property::firstOrFail();
        $u = User::create(['name' => 'Cetak', 'email' => 'cetak.uji@example.test', 'password' => Hash::make('Rahasia-2026x'),
            'is_active' => true, 'must_change_password' => false, 'property_id' => $p->id]);
        $u->roles()->sync([Role::where('slug', 'label-printer')->value('id')]);
        $this->actingAs($u->fresh())->get(BackupDatabase::getUrl())->assertForbidden();
    }

    public function test_command_creates_backup(): void
    {
        Storage::fake('local');
        $this->artisan('assetcare:backup-db')->assertSuccessful();
        $this->assertCount(1, app(BackupService::class)->list());
    }
}
