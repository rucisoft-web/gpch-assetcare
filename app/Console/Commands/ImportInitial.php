<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLabel;
use App\Models\AssetQuantityGroup;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;
use App\Support\CurrentProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Impor data awal dari workbook "Data awal Asset Care" (sheet Lokasi, Kategori, Aset).
 * Aman diulang: aset dikenali dari kolom Kunci (legacy_code), lokasi dari kodenya, kelompok dari lokasi+kategori+nama.
 * Semua aset masuk sebagai draft & belum terverifikasi.
 */
class ImportInitial extends Command
{
    protected $signature = 'assetcare:import-initial {file : workbook .xlsx} {--photos= : folder foto (kolom Foto)} {--dry-run : periksa saja, tidak menyimpan} {--user= : e-mail pencatat}';

    protected $description = 'Impor data awal aset, lokasi & foto dari workbook Data awal Asset Care';

    private const MONTHS = ['JANUARI' => 1, 'FEBRUARI' => 2, 'MARET' => 3, 'APRIL' => 4, 'MEI' => 5, 'JUNI' => 6, 'JULI' => 7, 'AGUSTUS' => 8,
        'SEPTEMBER' => 9, 'OKTOBER' => 10, 'NOVEMBER' => 11, 'DESEMBER' => 12];

    private array $stat = ['lokasi' => 0, 'kategori' => 0, 'aset' => 0, 'aset_sudah_ada' => 0, 'kelompok' => 0, 'kelompok_sudah_ada' => 0, 'foto' => 0, 'dilewati' => 0];

    private array $errors = [];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }
        $photos = $this->option('photos');
        if ($photos && ! is_dir($photos)) {
            $this->error("Folder foto tidak ditemukan: {$photos}");

            return self::FAILURE;
        }
        $property = Property::where('code', config('assetcare.property_code'))->first() ?? Property::orderBy('id')->firstOrFail();
        app(CurrentProperty::class)->set($property);
        $user = $this->option('user') ? User::where('email', $this->option('user'))->firstOrFail() : null;
        if ($user) {
            Auth::setUser($user);
        }

        $sheets = $this->read($file);
        foreach (['Lokasi', 'Aset'] as $s) {
            if (! isset($sheets[$s])) {
                $this->error("Sheet \"{$s}\" tidak ada di workbook.");

                return self::FAILURE;
            }
        }

        $dry = (bool) $this->option('dry-run');
        DB::beginTransaction();
        try {
            $this->categories($sheets['Kategori'] ?? [], $property);
            $locs = $this->locations($sheets['Lokasi'], $property);
            $this->assets($sheets['Aset'], $property, $locs, $photos, $user, $dry);
            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['Hasil', 'Jumlah'], collect($this->stat)->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all());
        foreach (array_slice($this->errors, 0, 50) as $e) {
            $this->warn($e);
        }
        if (count($this->errors) > 50) {
            $this->warn('… dan '.(count($this->errors) - 50).' pesan lain.');
        }
        $this->info($dry ? 'Uji coba selesai — tidak ada yang disimpan.' : 'Impor selesai. Aset berstatus draft & menunggu verifikasi fisik.');

        return self::SUCCESS;
    }

    /** @return array<string, list<array<string, mixed>>> baris per sheet, dikunci nama kolom */
    private function read(string $file): array
    {
        $reader = new Reader;
        $reader->open($file);
        $out = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $head = null;
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $vals = array_map(fn ($c) => $c->getValue(), $row->getCells());
                if ($head === null) {
                    $head = array_map(fn ($v) => trim((string) $v), $vals);

                    continue;
                }
                if (! array_filter($vals, fn ($v) => $v !== null && $v !== '')) {
                    continue;
                }
                $r = [];
                foreach ($head as $i => $h) {
                    if ($h !== '') {
                        $v = $vals[$i] ?? null;
                        $r[$h] = is_string($v) ? trim($v) : $v;
                    }
                }
                $rows[] = $r;
            }
            $out[$sheet->getName()] = $rows;
        }
        $reader->close();

        return $out;
    }

    private function categories(array $rows, Property $p): void
    {
        foreach ($rows as $r) {
            $code = (string) ($r['Kode'] ?? '');
            if ($code === '' || AssetCategory::withoutGlobalScopes()->where('property_id', $p->id)->where('code', $code)->exists()) {
                continue;
            }
            AssetCategory::withoutGlobalScopes()->create([
                'property_id' => $p->id, 'code' => $code, 'name' => $r['Nama'] ?: $code, 'group' => 'lainnya', 'default_criticality' => 'medium',
                'tracking_mode' => ($r['Mode pencatatan'] ?? '') === 'quantity' ? 'quantity' : 'individual',
                'default_pm_interval_days' => is_numeric($r['Interval PM default (hari)'] ?? null) ? (int) $r['Interval PM default (hari)'] : null, 'is_active' => true,
            ]);
            $this->stat['kategori']++;
        }
    }

    /** @return array<string, int> kode → id */
    private function locations(array $rows, Property $p): array
    {
        $ids = Location::withoutGlobalScopes()->where('property_id', $p->id)->pluck('id', 'code')->all();
        $pending = array_values(array_filter($rows, fn ($r) => ($r['Kode lokasi'] ?? '') !== ''));
        // induk dulu: ulangi sampai tidak ada kemajuan
        while ($pending) {
            $left = [];
            foreach ($pending as $r) {
                $code = (string) $r['Kode lokasi'];
                $parent = (string) ($r['Kode induk'] ?? '');
                if (isset($ids[$code])) {
                    continue;
                }
                if ($parent !== '' && ! isset($ids[$parent])) {
                    $left[] = $r;

                    continue;
                }
                $type = in_array($r['Jenis'] ?? '', ['building', 'zone', 'floor', 'room', 'venue', 'area', 'point'], true) ? $r['Jenis'] : 'area';
                $l = Location::withoutGlobalScopes()->create([
                    'property_id' => $p->id, 'parent_id' => $parent !== '' ? $ids[$parent] : null, 'code' => Str::limit($code, 40, ''),
                    'name' => (string) ($r['Nama'] ?: $code), 'location_type' => $type,
                    'floor_label' => isset($r['Label lantai']) && $r['Label lantai'] !== '' ? Str::limit((string) $r['Label lantai'], 20, '') : null,
                    'room_ref' => isset($r['No. kamar']) && $r['No. kamar'] !== '' ? Str::limit((string) $r['No. kamar'], 20, '') : null,
                    'is_active' => true,
                ]);
                $ids[$code] = $l->id;
                $this->stat['lokasi']++;
            }
            if (count($left) === count($pending)) {
                foreach ($left as $r) {
                    $this->errors[] = "Lokasi {$r['Kode lokasi']}: kode induk {$r['Kode induk']} tidak dikenal.";
                }
                break;
            }
            $pending = $left;
        }

        return $ids;
    }

    private function assets(array $rows, Property $p, array $locs, ?string $photos, ?User $user, bool $dry): void
    {
        $cats = AssetCategory::withoutGlobalScopes()->where('property_id', $p->id)->get()->keyBy('code');
        $depts = Department::withoutGlobalScopes()->where('property_id', $p->id)->pluck('id', 'code');
        $ops = array_flip(Asset::OPERATIONAL);
        $crit = array_flip(Asset::CRITICALITY);
        foreach ($rows as $i => $r) {
            $line = $i + 2;
            if (strcasecmp((string) ($r['Impor'] ?? ''), 'Ya') !== 0) {
                $this->stat['dilewati']++;

                continue;
            }
            $key = (string) ($r['Kunci'] ?? '');
            $cat = $cats[(string) ($r['Kode kategori'] ?? '')] ?? null;
            $loc = $locs[(string) ($r['Kode lokasi'] ?? '')] ?? null;
            $name = trim((string) ($r['Nama aset'] ?? ''));
            if (! $cat || ! $loc || $name === '' || $key === '') {
                $this->errors[] = "Aset baris {$line}: ".(! $cat ? 'kategori' : (! $loc ? 'kode lokasi' : ($name === '' ? 'nama' : 'kunci'))).' tidak valid — dilewati.';
                $this->stat['dilewati']++;

                continue;
            }
            $qty = is_numeric($r['Jumlah'] ?? null) ? (float) $r['Jumlah'] : 1;
            $files = $this->photoFiles((string) ($r['Foto'] ?? ''), $photos, $line);

            if (($r['Mode'] ?? '') === 'Kelompok') {
                $g = AssetQuantityGroup::withoutGlobalScopes()->where(['property_id' => $p->id, 'location_id' => $loc, 'category_id' => $cat->id, 'name' => Str::limit($name, 255, '')])->first();
                if ($g) {
                    $this->stat['kelompok_sudah_ada']++;

                    continue;
                }
                $g = AssetQuantityGroup::create([
                    'property_id' => $p->id, 'location_id' => $loc, 'category_id' => $cat->id, 'name' => Str::limit($name, 255, ''),
                    'unit' => Str::limit((string) ($r['Satuan'] ?: 'pcs'), 20, ''),
                    'expected_qty' => is_numeric($r['Jumlah standar'] ?? null) ? (float) $r['Jumlah standar'] : null, 'on_hand' => null, 'status' => 'draft',
                ]);
                $g->recordCount($qty, 'Data awal: '.Str::limit((string) ($r['Sumber'] ?? ''), 200, ''), 'count');
                AssetLabel::issueFor(null, $g);
                $this->stat['kelompok']++;

                continue;
            }

            $n = max(1, (int) round($qty));
            for ($k = 1; $k <= $n; $k++) {
                $legacy = $n > 1 ? "{$key}#{$k}" : $key;
                if (Asset::withoutGlobalScopes()->where('property_id', $p->id)->where('legacy_code', $legacy)->exists()) {
                    $this->stat['aset_sudah_ada']++;

                    continue;
                }
                $a = Asset::create([
                    'property_id' => $p->id, 'name' => Str::limit($name, 255, ''), 'category_id' => $cat->id,
                    'brand' => $this->str($r['Merek'] ?? null), 'model' => $this->str($r['Model / tipe'] ?? null),
                    'serial_number' => $this->str($r['No. seri'] ?? null), 'location_id' => $loc,
                    'department_id' => $depts[(string) ($r['Departemen'] ?? '')] ?? null,
                    'custodian_unit' => $this->str($r['Unit penanggung jawab'] ?? null),
                    'condition_grade' => preg_match('/^([A-E])\b/', (string) ($r['Kondisi'] ?? ''), $m) ? $m[1] : null,
                    'operational_status' => $ops[$r['Status operasional'] ?? ''] ?? 'serviceable',
                    'criticality' => $crit[$r['Kritikalitas'] ?? ''] ?? $cat->default_criticality,
                    'pm_interval_days' => is_numeric($r['Interval PM (hari)'] ?? null) ? (int) $r['Interval PM (hari)'] : $cat->default_pm_interval_days,
                    'specification' => $this->str($r['Spesifikasi'] ?? null, 5000),
                    'purchase_date' => $this->monthDate($r['Tgl pembelian'] ?? null),
                    'ownership' => 'milik', 'lifecycle_status' => 'draft', 'verification_status' => 'unverified',
                    'legacy_code' => $legacy, 'migration_source' => Str::limit((string) ($r['Sumber'] ?? 'Data awal'), 255, ''),
                    'notes' => $this->notes($r), 'created_by' => $user?->id,
                ]);
                AssetLabel::issueFor($a);
                if (is_numeric($r['Harga (Rp)'] ?? null) && $r['Harga (Rp)'] > 0) {
                    $a->financial()->updateOrCreate([], ['replacement_estimate' => $r['Harga (Rp)']]);
                }
                if ($dry) {
                    $this->stat['foto'] += count($files);
                } else {
                    $this->attachPhotos($a, $files, $user);
                }
                $this->stat['aset']++;
            }
        }
    }

    private function photoFiles(string $list, ?string $dir, int $line): array
    {
        if ($list === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/\s*;\s*/', $list) as $f) {
            if ($f === '' || ! $dir) {
                continue;
            }
            $path = rtrim($dir, '/').'/'.basename($f);
            if (is_file($path)) {
                $out[] = $path;
            } else {
                $this->errors[] = "Aset baris {$line}: foto {$f} tidak ditemukan.";
            }
        }

        return $out;
    }

    private function attachPhotos(Asset $a, array $files, ?User $user): void
    {
        $disk = Storage::disk('local');
        foreach ($files as $i => $src) {
            $sum = hash_file('sha256', $src);
            $path = 'assets/photos/awal/'.substr($sum, 0, 2).'/'.$sum.'.'.strtolower(pathinfo($src, PATHINFO_EXTENSION) ?: 'jpg');
            if (! $disk->exists($path)) {
                $disk->put($path, file_get_contents($src));
            }
            Attachment::create([
                'attachable_type' => $a->getMorphClass(), 'attachable_id' => $a->id, 'kind' => 'photo', 'disk' => 'local', 'path' => $path,
                'original_name' => basename($src), 'mime' => mime_content_type($src) ?: 'image/jpeg', 'size' => filesize($src), 'checksum' => $sum,
                'note' => 'Foto dari file inventaris (data awal)', 'visibility' => 'internal', 'uploaded_by' => $user?->id,
            ]);
            if ($i === 0 && ! $a->photo_path) {
                $a->forceFill(['photo_path' => $path])->saveQuietly();
            }
            $this->stat['foto']++;
        }
    }

    private function notes(array $r): ?string
    {
        $parts = [];
        if ($v = $this->str($r['Catatan / remark terbaru'] ?? null, 2000)) {
            $parts[] = 'Remark inventaris: '.$v;
        }
        if ($v = $this->str($r['Remark lama'] ?? null, 2000)) {
            $parts[] = 'Remark sebelumnya: '.$v;
        }
        if ($v = $this->str($r['Berlaku s.d.'] ?? null)) {
            $parts[] = 'Berlaku s.d.: '.$v;
        }
        if (($v = $this->str($r['Tgl pembelian'] ?? null)) && ! $this->monthDate($v)) {
            $parts[] = 'Pembelian: '.$v;
        }
        if ($v = $this->str($r['Diasumsikan'] ?? null, 1000)) {
            $parts[] = 'Diasumsikan saat data awal (koreksi saat identifikasi): '.$v;
        }
        if ($v = $this->str($r['Perlu dicek'] ?? null, 2000)) {
            $parts[] = 'Perlu dicek: '.$v;
        }

        return $parts ? implode("\n", $parts) : null;
    }

    private function str(mixed $v, int $max = 255): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d-m-Y');
        }
        $v = trim((string) ($v ?? ''));

        return $v === '' ? null : Str::limit($v, $max, '');
    }

    /** "AGUSTUS 2019" / DateTime → tanggal; selain itu null (teks disimpan di catatan). */
    private function monthDate(mixed $v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_string($v) && preg_match('/^([A-Z]+)\s+(\d{4})$/i', trim($v), $m) && isset(self::MONTHS[strtoupper($m[1])])) {
            return sprintf('%04d-%02d-01', $m[2], self::MONTHS[strtoupper($m[1])]);
        }

        return null;
    }
}
