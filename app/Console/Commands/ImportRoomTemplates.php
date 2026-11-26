<?php

namespace App\Console\Commands;

use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomAssetTemplate;
use App\Models\RoomException;
use App\Models\RoomType;
use App\Models\User;
use App\Services\RoomTemplateService;
use App\Support\CurrentProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Impor workbook "Template kamar" (sheet Tipe kamar, Kamar, Template, Pengecualian):
 * tipe kamar → tipe pada lokasi kamar (kamar baru dibuat di bawah lantainya) → template published per tipe → pengecualian per kamar.
 * --generate menjalankan "Generate aset kamar": slot dibuat, aset lama sekategori di kamar (mis. AC & TV data awal) dicocokkan,
 * sisanya dibuat sebagai aset draft. Aman diulang.
 */
class ImportRoomTemplates extends Command
{
    protected $signature = 'assetcare:import-room-templates {file : workbook .xlsx} {--generate : buat/cocokkan aset per kamar sesuai template} {--dry-run : periksa saja, tidak menyimpan} {--user= : e-mail pelaksana (default Super Admin pertama)}';

    protected $description = 'Impor tipe kamar, template aset kamar & pengecualian per kamar; opsional generate aset kamar';

    private array $stat = ['tipe kamar' => 0, 'kamar diberi tipe' => 0, 'kamar baru' => 0, 'template baru' => 0, 'template tetap' => 0, 'item template' => 0, 'pengecualian' => 0];

    private array $warn = [];

    public function handle(RoomTemplateService $svc): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }
        $property = Property::where('code', config('assetcare.property_code'))->first() ?? Property::orderBy('id')->firstOrFail();
        app(CurrentProperty::class)->set($property);
        $user = $this->option('user')
            ? User::where('email', $this->option('user'))->firstOrFail()
            : User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->where('is_active', true)->orderBy('id')->firstOrFail();
        Auth::setUser($user);

        $s = $this->read($file);
        foreach (['Tipe kamar', 'Kamar', 'Template'] as $need) {
            if (! isset($s[$need])) {
                $this->error("Sheet \"{$need}\" tidak ada di workbook.");

                return self::FAILURE;
            }
        }
        $dry = (bool) $this->option('dry-run');

        DB::beginTransaction();
        try {
            $types = $this->types($s['Tipe kamar'], $property);
            $rooms = $this->rooms($s['Kamar'], $property, $types);
            $templates = $this->templates($s['Template'], $property, $types, $user);
            $this->exceptions($s['Pengecualian'] ?? [], $rooms, $user);
            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $this->table(['Hasil', 'Jumlah'], collect($this->stat)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($this->option('generate') && ! $dry) {
            $this->generate($svc, $templates, $user);
        }
        foreach (array_slice($this->warn, 0, 40) as $w) {
            $this->warn($w);
        }
        if (count($this->warn) > 40) {
            $this->warn('… dan '.(count($this->warn) - 40).' pesan lain.');
        }
        $this->info($dry ? 'Uji coba selesai — tidak ada yang disimpan.' : 'Selesai.'.($this->option('generate') ? '' : ' Jalankan lagi dengan --generate untuk membuat aset per kamar, atau pakai menu Generate aset kamar.'));

        return self::SUCCESS;
    }

    /** @return array<string, RoomType> */
    private function types(array $rows, Property $p): array
    {
        $out = [];
        foreach ($rows as $r) {
            $code = Str::limit((string) ($r['Kode tipe'] ?? ''), 20, '');
            if ($code === '') {
                continue;
            }
            $t = RoomType::firstOrNew(['property_id' => $p->id, 'code' => $code]);
            $t->fill(['name' => (string) ($r['Nama tipe'] ?: $code), 'bed_config' => $this->str($r['Konfigurasi ranjang'] ?? null, 40), 'is_active' => true]);
            if ($t->isDirty()) {
                $t->save();
                $this->stat['tipe kamar']++;
            }
            $out[$code] = $t;
        }

        return $out;
    }

    /** @return array<string, Location> kode lokasi → kamar */
    private function rooms(array $rows, Property $p, array $types): array
    {
        $out = [];
        foreach ($rows as $r) {
            $code = (string) ($r['Kode lokasi'] ?? '');
            $type = $types[(string) ($r['Kode tipe'] ?? '')] ?? null;
            if ($code === '') {
                continue;
            }
            if (! $type) {
                $this->warn[] = "Kamar {$code}: kode tipe \"{$r['Kode tipe']}\" tidak ada di sheet Tipe kamar.";

                continue;
            }
            $room = Location::where('code', $code)->first();
            if (! $room) {
                $floor = Location::where('code', (string) ($r['Kode lantai'] ?? ''))->first();
                if (! $floor) {
                    $this->warn[] = "Kamar {$code}: lantai {$r['Kode lantai']} tidak ditemukan.";

                    continue;
                }
                $no = (string) ($r['No. kamar'] ?? Str::afterLast($code, '-'));
                $room = Location::create(['property_id' => $p->id, 'parent_id' => $floor->id, 'code' => $code, 'name' => 'Kamar '.$no,
                    'location_type' => 'room', 'room_ref' => $no, 'room_type_id' => $type->id, 'is_active' => true]);
                $this->stat['kamar baru']++;
            } elseif ((int) $room->room_type_id !== (int) $type->id) {
                $room->room_type_id = $type->id;
                $room->room_ref = $room->room_ref ?: (string) ($r['No. kamar'] ?? '');
                $room->save();
                $this->stat['kamar diberi tipe']++;
            }
            $out[$code] = $room;
        }

        return $out;
    }

    /** @return array<string, RoomAssetTemplate> kode tipe → template published */
    private function templates(array $rows, Property $p, array $types, User $user): array
    {
        $cats = AssetCategory::pluck('id', 'code')->all();
        $depts = Department::pluck('id', 'code')->all();
        $out = [];
        foreach (collect($rows)->groupBy(fn ($r) => (string) ($r['Kode tipe'] ?? '')) as $tcode => $items) {
            $type = $types[$tcode] ?? null;
            if (! $type) {
                $this->warn[] = "Template: kode tipe \"{$tcode}\" tidak dikenal.";

                continue;
            }
            $wanted = [];
            foreach ($items->values() as $i => $r) {
                $cat = $cats[(string) ($r['Kode kategori'] ?? '')] ?? null;
                $qty = (int) ($r['Jumlah standar'] ?? 0);
                $key = Str::limit(Str::slug((string) ($r['Kunci item'] ?? $r['Nama standar'] ?? '')), 40, '');
                if (! $cat || $qty < 1 || $key === '') {
                    $this->warn[] = "Template {$tcode}: baris \"".($r['Nama standar'] ?? '?').'" dilewati (kategori tidak dikenal atau jumlah 0).';

                    continue;
                }
                $mode = ($r['Mode'] ?? '') === 'quantity' ? 'quantity' : 'individual';
                $wanted[$key] = ['item_key' => $key, 'category_id' => $cat, 'standard_name' => (string) $r['Nama standar'], 'quantity' => $qty,
                    'mode' => $mode, 'unit' => (string) ($r['Satuan'] ?? 'unit') ?: 'unit', 'default_department_id' => $depts[(string) ($r['Departemen'] ?? '')] ?? null,
                    'specification' => $this->str($r['Area'] ?? null, 1000) ? 'Area: '.$r['Area'] : null, 'sort' => ($i + 1) * 10];
            }
            $code = Str::limit('TPL-'.$tcode, 30, '');
            $current = RoomAssetTemplate::where('code', $code)->where('status', 'published')->orderByDesc('version')->with('items')->first();
            $sig = fn ($list) => collect($list)->map(fn ($x) => [$x['item_key'], (int) $x['category_id'], $x['standard_name'], (int) $x['quantity'], $x['mode']])->sortBy(0)->values()->all();
            if ($current && $sig($current->items->map(fn ($x) => $x->only(['item_key', 'category_id', 'standard_name', 'quantity', 'mode']))->all()) === $sig($wanted)) {
                $out[$tcode] = $current;
                $this->stat['template tetap']++;

                continue;
            }
            $version = (int) RoomAssetTemplate::where('code', $code)->max('version') + 1;
            $tpl = RoomAssetTemplate::create(['property_id' => $p->id, 'code' => $code, 'version' => $version, 'name' => 'Template '.$type->name,
                'room_type_id' => $type->id, 'status' => 'draft', 'prepared_by' => $user->id,
                'notes' => 'Diimpor dari workbook Template kamar ('.basename($this->argument('file')).'). Jumlah standar = jumlah terbanyak pada kamar bertipe sama.']);
            foreach ($wanted as $w) {
                $tpl->items()->create($w);
                $this->stat['item template']++;
            }
            $tpl->update(['status' => 'published', 'published_at' => now(), 'reviewed_by' => $user->id]);
            if ($current) {
                RoomAssetTemplate::whereKey($current->id)->update(['status' => 'archived']);
            }
            $out[$tcode] = $tpl;
            $this->stat['template baru']++;
        }

        return $out;
    }

    private function exceptions(array $rows, array $rooms, User $user): void
    {
        $cats = AssetCategory::pluck('id', 'code')->all();
        foreach ($rows as $r) {
            $room = $rooms[(string) ($r['Kode lokasi'] ?? '')] ?? null;
            $type = (string) ($r['Jenis'] ?? '');
            $key = Str::limit(Str::slug((string) ($r['Kunci item'] ?? '')), 40, '');
            if (! $room || $key === '' || ! isset(RoomException::TYPES[$type])) {
                $this->warn[] = 'Pengecualian '.($r['Kode lokasi'] ?? '?').' / '.($r['Kunci item'] ?? '?').' dilewati (kamar, item atau jenis tidak dikenal).';

                continue;
            }
            RoomException::updateOrCreate(['room_id' => $room->id, 'item_key' => $key], [
                'type' => $type, 'quantity' => in_array($type, ['quantity', 'added'], true) ? (int) ($r['Jumlah aktual'] ?? 0) : null,
                'substitute_category_id' => $type === 'added' ? ($cats[(string) ($r['Kode kategori'] ?? '')] ?? null) : null,
                'substitute_name' => $type === 'added' ? $this->str($r['Nama item'] ?? null) : null,
                'reason' => $this->str($r['Alasan'] ?? null) ?: 'Inventaris kamar', 'recorded_by' => $user->id,
            ]);
            $this->stat['pengecualian']++;
        }
    }

    /** Generate per template: aset lama sekategori di kamar dicocokkan otomatis, sisanya dibuat draft. */
    private function generate(RoomTemplateService $svc, array $templates, User $user): void
    {
        $rows = [];
        foreach ($templates as $tcode => $tpl) {
            $roomIds = Location::where('room_type_id', $tpl->room_type_id)->whereIn('location_type', ['room', 'venue'])->pluck('id')->all();
            if (! $roomIds) {
                continue;
            }
            $preview = $svc->preview($tpl, $roomIds);
            if (! collect($preview)->sum(fn ($p) => $p['counts']['create'] + $p['counts']['candidate'])) {
                $rows[] = [$tcode, count($roomIds), '—', 0, 0, 'sudah lengkap'];

                continue;
            }
            $decisions = [];
            foreach ($preview as $roomId => $p) {
                $used = [];
                foreach ($p['rows'] as $r) {
                    if ($r['state'] !== 'candidate') {
                        continue;
                    }
                    $pick = collect($r['candidates'])->first(fn ($c) => ! in_array($c['id'], $used, true));
                    if ($pick) {
                        $used[] = $pick['id'];
                        $decisions[$roomId.'|'.$r['item_key'].'|'.$r['unit_position']] = $pick['id'];
                    }
                }
            }
            $batch = $svc->createBatch($tpl, $roomIds, $preview, $user, 'import-'.$tpl->code.'-v'.$tpl->version.'-'.md5(json_encode(array_map(fn ($x) => $x['signature'], $preview))));
            $batch = $svc->commit($batch, $decisions, $user);
            $rows[] = [$tcode, count($roomIds), $batch->number, $batch->created_assets, $batch->matched_assets, $batch->status];
            foreach ($batch->results()->where('status', '!=', 'ok')->get() as $res) {
                $this->warn[] = "{$batch->number} kamar #{$res->room_id}: {$res->status} — {$res->message}";
            }
        }
        $this->table(['Tipe', 'Kamar', 'Batch', 'Aset baru', 'Aset lama dicocokkan', 'Status'], $rows);
        if ($rows) {
            $this->line('Aset baru berstatus draft & belum terverifikasi. Label QR dibuat; cetak lewat menu Cetak label QR.');
        }
    }

    /** @return array<string, list<array<string, mixed>>> */
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

    private function str(mixed $v, int $max = 255): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : Str::limit($v, $max, '');
    }
}
