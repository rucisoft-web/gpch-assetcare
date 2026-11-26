<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetLabel;
use App\Models\AssetQuantityGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Terbitkan token label QR untuk aset/kelompok yang belum pernah punya label
 * (mis. data yang masuk lewat impor SQL; token dienkripsi dengan APP_KEY server sehingga harus dibuat di server).
 */
class LabelsEnsure extends Command
{
    protected $signature = 'assetcare:labels-ensure';

    protected $description = 'Buat label QR untuk aset & kelompok jumlah yang belum memiliki label';

    public function handle(): int
    {
        $n = 0;
        $none = fn ($col) => fn ($q) => $q->select(DB::raw(1))->from('asset_labels')->whereColumn("asset_labels.{$col}", 'x.id');
        Asset::withoutGlobalScopes()->from('assets as x')->select('x.*')
            ->whereNotIn('x.lifecycle_status', ['retired', 'disposed', 'lost'])->whereNotExists($none('asset_id'))
            ->chunkById(500, function ($rows) use (&$n) {
                foreach ($rows as $a) {
                    AssetLabel::issueFor($a);
                    $n++;
                }
            }, 'x.id', 'id');
        AssetQuantityGroup::withoutGlobalScopes()->from('asset_quantity_groups as x')->select('x.*')->whereNotExists($none('quantity_group_id'))
            ->chunkById(500, function ($rows) use (&$n) {
                foreach ($rows as $g) {
                    AssetLabel::issueFor(null, $g);
                    $n++;
                }
            }, 'x.id', 'id');
        $this->info("Label baru: {$n}.");

        return self::SUCCESS;
    }
}
