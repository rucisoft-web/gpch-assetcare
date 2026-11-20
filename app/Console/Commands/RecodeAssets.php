<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\InstallationEvent;
use App\Support\AssetCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ubah kode aset lama (GPCH-AST-000123) menjadi LOKASI-JENIS-URUT (MB-08-819-TV-01).
 * QR pada label tidak berubah (QR memakai token), tetapi teks kode di label yang sudah dicetak menjadi tidak sesuai
 * sehingga label tersebut ditandai "perlu label ulang". Kode lama disimpan dan tetap bisa dicari.
 */
class RecodeAssets extends Command
{
    protected $signature = 'assetcare:recode-assets {--dry-run : Tampilkan contoh tanpa menyimpan} {--all : Termasuk aset yang kodenya sudah berformat lokasi}';

    protected $description = 'Ubah kode aset menjadi format lokasi-jenis-urut (mis. MB-08-819-TV-01)';

    public function handle(): int
    {
        $assets = Asset::withoutGlobalScopes()->with(['location', 'category', 'slotAssignment.slot', 'activeLabel'])->get();
        $plan = [];
        $counters = [];
        $skipped = 0;
        $sorted = $assets->sortBy(fn (Asset $a) => implode('|', [
            $a->property_id, $a->location?->sort_key ?? 'zzz', $a->category?->code ?? '',
            str_pad((string) ($a->slotAssignment?->slot?->item_key ?? ''), 40), str_pad((string) ($a->slotAssignment?->slot?->unit_position ?? 0), 4, '0', STR_PAD_LEFT),
            $a->name, str_pad((string) $a->id, 10, '0', STR_PAD_LEFT),
        ]));
        foreach ($sorted as $a) {
            $prefix = AssetCode::prefix($a->location_id, $a->category_id);
            if (! $prefix) {
                $skipped++;

                continue;
            }
            if (! $this->option('all') && preg_match('/^'.preg_quote($prefix, '/').'-\d{2,}$/', (string) $a->asset_code)) {
                $n = (int) substr($a->asset_code, strlen($prefix) + 1);
                $counters[$a->property_id][$prefix] = max($counters[$a->property_id][$prefix] ?? 0, $n);

                continue;
            }
            $plan[] = [$a, $prefix];
        }
        // nomor urut dilanjutkan setelah kode berformat lokasi yang sudah ada
        $rows = [];
        foreach ($plan as [$a, $prefix]) {
            $n = ($counters[$a->property_id][$prefix] ?? 0) + 1;
            $counters[$a->property_id][$prefix] = $n;
            $rows[] = [$a, sprintf('%s-%02d', $prefix, $n)];
        }

        $this->info(count($rows).' aset akan diberi kode baru'.($skipped ? '; '.$skipped.' dilewati (lokasi/kategori kosong)' : '').'.');
        $this->table(['Kode lama', 'Kode baru', 'Nama'], collect($rows)->take(15)->map(fn ($r) => [$r[0]->asset_code, $r[1], $r[0]->name])->all());
        if ($this->option('dry-run') || ! $rows) {
            return self::SUCCESS;
        }

        $relabel = 0;
        DB::transaction(function () use ($rows, $counters, &$relabel) {
            // dua tahap agar tidak bentrok dengan indeks unik
            foreach ($rows as [$a]) {
                DB::table('assets')->where('id', $a->id)->update(['asset_code' => 'TMP-'.$a->id]);
            }
            foreach ($rows as [$a, $code]) {
                DB::table('assets')->where('id', $a->id)->update([
                    'asset_code' => $code, 'former_codes' => AssetCode::appendFormer($a->former_codes, $a->asset_code), 'updated_at' => now(),
                ]);
                $label = $a->activeLabel;
                $last = $label?->printItems()->latest('id')->first();
                if ($last && $last->code_snapshot !== $code) {
                    InstallationEvent::create([
                        'asset_label_id' => $label->id, 'asset_id' => $a->id, 'location_id' => $a->location_id, 'print_item_id' => $last->id,
                        'status' => 'needs_relabel', 'note' => 'Kode aset diganti ke format lokasi: '.$code, 'occurred_at' => now(),
                    ]);
                    app(\App\Services\LabelService::class)->syncInstallStatus($label);
                    $relabel++;
                }
            }
            foreach ($counters as $propertyId => $byPrefix) {
                foreach ($byPrefix as $prefix => $n) {
                    $key = ['property_id' => $propertyId, 'prefix' => 'A:'.$prefix, 'year' => 0];
                    $cur = (int) DB::table('sequences')->where($key)->value('last_number');
                    DB::table('sequences')->updateOrInsert($key, ['last_number' => max($cur, $n), 'updated_at' => now(), 'created_at' => now()]);
                }
            }
        });
        $this->info(count($rows).' kode aset diperbarui. Kode lama tetap bisa dicari.');
        if ($relabel) {
            $this->warn($relabel.' label yang sudah dicetak ditandai "perlu label ulang" karena teks kodenya berubah (QR tetap berlaku).');
        }

        return self::SUCCESS;
    }
}
