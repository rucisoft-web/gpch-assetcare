<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Backup database ke berkas .sql (bertanggal) yang dikemas ZIP, disimpan privat di storage/app/private/backups
 * dan bisa diunduh bagian IT dari menu Pengaturan › Backup database.
 * Memakai mysqldump bila tersedia; bila tidak (mis. exec dimatikan hosting), memakai pembuang SQL bawaan PHP.
 */
class BackupService
{
    public const DIR = 'backups';

    public const KEEP_DAYS = 30;

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /** @return string path ZIP relatif terhadap disk local */
    public function create(?User $by = null, string $source = 'manual'): string
    {
        $tz = config('assetcare.display_timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $db = (string) config('database.connections.'.config('database.default').'.database');
        $base = 'asset-care-gpch_db_'.$now->format('Y-m-d_His');
        $tmpDir = storage_path('app/backup-tmp');
        @mkdir($tmpDir, 0775, true);
        $sqlPath = $tmpDir.'/'.$base.'.sql';

        $header = "-- Backup database Asset Care GPCH\n"
            .'-- Tanggal backup : '.$now->translatedFormat('l, d F Y H:i:s').' ('.$tz.")\n"
            .'-- Database       : '.basename($db)."\n"
            .'-- Dibuat oleh    : '.($by?->name ?? 'Jadwal otomatis')." ({$source})\n"
            ."-- Pulihkan       : mysql -u USER -p NAMA_DATABASE < {$base}.sql\n\n";
        file_put_contents($sqlPath, $header);

        try {
            $method = $this->dumpWithMysqldump($sqlPath) ? 'mysqldump' : $this->dumpWithPhp($sqlPath);
            file_put_contents($sqlPath, "\n-- Selesai ".Carbon::now($tz)->format('Y-m-d H:i:s')." ({$method})\n", FILE_APPEND);

            $zipRel = self::DIR.'/'.$base.'.zip';
            $this->disk()->makeDirectory(self::DIR);
            $zip = new ZipArchive;
            if ($zip->open($this->disk()->path($zipRel), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Berkas ZIP tidak dapat dibuat.');
            }
            $zip->addFile($sqlPath, $base.'.sql');
            $zip->setCompressionName($base.'.sql', ZipArchive::CM_DEFLATE);
            $zip->close();
        } finally {
            @unlink($sqlPath);
        }

        Log::info('Backup database dibuat', ['file' => $zipRel, 'by' => $by?->id, 'source' => $source]);
        $this->prune();

        return $zipRel;
    }

    /** @return Collection<int, array{name:string,path:string,size:int,time:Carbon}> terbaru di atas */
    public function list(): Collection
    {
        $tz = config('assetcare.display_timezone', 'Asia/Jakarta');

        return collect($this->disk()->files(self::DIR))
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->map(fn ($p) => ['name' => basename($p), 'path' => $p, 'size' => $this->disk()->size($p),
                'time' => Carbon::createFromTimestamp($this->disk()->lastModified($p), $tz)])
            ->sortByDesc('time')->values();
    }

    public function path(string $name): string
    {
        if (! preg_match('/^[A-Za-z0-9_.-]+\.zip$/', $name) || ! $this->disk()->exists(self::DIR.'/'.$name)) {
            throw WorkflowException::invalid('Berkas backup tidak ditemukan.');
        }

        return self::DIR.'/'.$name;
    }

    public function delete(string $name): void
    {
        $this->disk()->delete($this->path($name));
    }

    /** Hapus backup yang lebih tua dari KEEP_DAYS hari, tetapi selalu sisakan 5 terbaru. */
    public function prune(): int
    {
        $old = $this->list()->slice(5)->filter(fn ($f) => $f['time']->lt(now()->subDays(self::KEEP_DAYS)));
        $old->each(fn ($f) => $this->disk()->delete($f['path']));

        return $old->count();
    }

    private function dumpWithMysqldump(string $sqlPath): bool
    {
        $c = config('database.connections.'.config('database.default'));
        if (($c['driver'] ?? '') !== 'mysql' || ! function_exists('proc_open')) {
            return false;
        }
        try {
            $bin = trim((string) Process::run('command -v mysqldump')->output());
            if ($bin === '') {
                return false;
            }
            $args = [$bin, '-h', $c['host'] ?? '127.0.0.1', '-P', (string) ($c['port'] ?? 3306), '-u', (string) $c['username'],
                '--single-transaction', '--quick', '--routines', '--no-tablespaces', '--default-character-set=utf8mb4', $c['database']];
            if (! empty($c['unix_socket'])) {
                array_splice($args, 1, 4, ['--socket='.$c['unix_socket']]);
            }
            $out = fopen($sqlPath, 'a');
            $r = Process::env(['MYSQL_PWD' => (string) $c['password']])->timeout(600)
                ->run($args, function (string $type, string $buffer) use ($out) {
                    if ($type === 'out') {
                        fwrite($out, $buffer);
                    }
                });
            fclose($out);

            if (! $r->successful()) {
                Log::warning('mysqldump gagal, memakai pembuang PHP', ['err' => $r->errorOutput()]);
                // buang hasil parsial: tulis ulang hanya header
                $lines = file($sqlPath);
                file_put_contents($sqlPath, implode('', array_slice($lines, 0, 6)));

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('mysqldump tidak dapat dijalankan', ['err' => $e->getMessage()]);

            return false;
        }
    }

    /** Pembuang SQL tanpa program luar (MySQL atau SQLite). */
    private function dumpWithPhp(string $sqlPath): string
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $out = fopen($sqlPath, 'a');
        if ($driver === 'mysql') {
            fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tables = array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'));
        } else {
            fwrite($out, "PRAGMA foreign_keys=OFF;\n\n");
            $tables = array_column(array_map(fn ($r) => (array) $r, DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")), 'name');
        }
        foreach ($tables as $t) {
            $q = $driver === 'mysql' ? '`'.str_replace('`', '``', $t).'`' : '"'.str_replace('"', '""', $t).'"';
            $create = $driver === 'mysql'
                ? array_values((array) DB::selectOne('SHOW CREATE TABLE '.$q))[1]
                : DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$t])->sql;
            fwrite($out, "-- Tabel {$t}\nDROP TABLE IF EXISTS {$q};\n{$create};\n");
            $rows = [];
            foreach (DB::table($t)->cursor() as $row) {
                $vals = array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), (array) $row);
                $rows[] = '('.implode(',', $vals).')';
                if (count($rows) >= 200) {
                    fwrite($out, "INSERT INTO {$q} VALUES\n".implode(",\n", $rows).";\n");
                    $rows = [];
                }
            }
            if ($rows) {
                fwrite($out, "INSERT INTO {$q} VALUES\n".implode(",\n", $rows).";\n");
            }
            fwrite($out, "\n");
        }
        fwrite($out, $driver === 'mysql' ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n");
        fclose($out);

        return 'php';
    }
}
