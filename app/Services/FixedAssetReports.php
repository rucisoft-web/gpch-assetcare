<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\FiscalGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Laporan aset tetap (penyusutan dua buku) dan register dokumen. Tahun buku = tahun tanggal "sampai". */
class FixedAssetReports
{
    public function __construct(protected DepreciationService $dep, protected DocumentService $docs) {}

    /** @return array{headers:array, rows:array} */
    public function build(string $report, int $year, ?User $u = null): array
    {
        return match ($report) {
            'penyusutan' => $this->commercial($year),
            'fiskal' => $this->fiscal($year),
            'rekonsiliasi' => $this->reconciliation($year),
            'mutasi' => $this->movement($year),
            'kapitalisasi' => $this->capitalisation(),
            'dok_kurang' => $this->missing(),
            'dok_berakhir' => $this->expiring($u),
            'tanpa_asuransi' => $this->uninsured(),
        };
    }

    protected function className(array $s): string
    {
        $c = $s['params']['class'];

        return $c ? $c->code.' '.$c->name : '(tanpa kelas)';
    }

    protected function commercial(int $year): array
    {
        return [
            'headers' => ['Kode', 'Nama', 'Kelas', 'Mulai susut', 'Umur (bln)', 'Biaya perolehan', 'Residu', 'Akum. awal '.$year, 'Penyusutan '.$year, 'Akum. akhir', 'Penurunan nilai', 'Nilai buku akhir', 'Keterangan'],
            'rows' => $this->dep->register($year)->map(fn ($s) => [
                $s['asset']->asset_code, $s['asset']->name, $this->className($s), $s['params']['commercial_start']?->format('m/Y'), $s['params']['commercial_life'],
                $s['params']['cost'], $s['params']['residual'], $s['commercial']['accum_open'], $s['commercial']['depreciation'], $s['commercial']['accum_close'],
                $s['params']['impairment'] ?: '', $s['commercial']['nbv_close'], $s['disposed_in_year'] ? 'dilepas '.$s['params']['disposal']->format('m/Y') : '',
            ])->all(),
        ];
    }

    /** Daftar penyusutan fiskal ala Lampiran 1A SPT PPh Badan, dikelompokkan per kelompok harta. */
    protected function fiscal(int $year): array
    {
        $groups = FiscalGroup::orderBy('sort')->get()->keyBy('code');
        $rows = [];
        $this->dep->register($year)->groupBy(fn ($s) => $s['params']['fiscal_group'] ?? '-')
            ->sortBy(fn ($g, $k) => $groups->get($k)?->sort ?? 999)
            ->each(function (Collection $items, $code) use (&$rows, $groups) {
                $g = $groups->get($code);
                foreach ($items->sortBy(fn ($s) => $s['asset']->asset_code) as $s) {
                    $rows[] = [$code.' '.($g?->name ?? ''), $s['asset']->asset_code, $s['asset']->name, $s['params']['fiscal_start']?->format('m/Y'),
                        $s['params']['fiscal_life'], $s['params']['cost'], $s['fiscal']['accum_open'], $s['fiscal']['depreciation'], $s['fiscal']['accum_close'], $s['fiscal']['nbv_close']];
                }
                $rows[] = ['Jumlah '.$code, '', '', '', '', $items->sum('params.cost'), $items->sum('fiscal.accum_open'), $items->sum('fiscal.depreciation'), $items->sum('fiscal.accum_close'), $items->sum('fiscal.nbv_close')];
            });

        return [
            'headers' => ['Kelompok harta', 'Kode', 'Nama', 'Bulan perolehan', 'Masa manfaat (bln)', 'Harga perolehan', 'Akum. awal', 'Penyusutan fiskal', 'Akum. akhir', 'Nilai sisa buku fiskal'],
            'rows' => $rows,
        ];
    }

    protected function reconciliation(int $year): array
    {
        $rate = (float) $this->dep->policy()->deferred_tax_rate / 100;
        $rows = $this->dep->register($year)->groupBy(fn ($s) => $this->className($s))->sortKeys()
            ->map(function (Collection $g, $class) use ($rate) {
                $tmp = $g->sum('temporary_difference');

                return [$class, $g->count(), $g->sum('commercial.depreciation'), $g->sum('fiscal.depreciation'), round($g->sum('correction'), 2),
                    $g->sum('commercial.nbv_close'), $g->sum('fiscal.nbv_close'), round($tmp, 2), round($tmp * $rate, 2)];
            })->values();
        $total = ['Jumlah', $rows->sum(1)];
        foreach (range(2, 8) as $i) {
            $total[] = round($rows->sum($i), 2);
        }

        return [
            'headers' => ['Kelas', 'Aset', 'Penyusutan komersial', 'Penyusutan fiskal', 'Koreksi fiskal (+/−)', 'NBV komersial', 'NBV fiskal', 'Beda temporer', 'Pajak tangguhan '.rtrim(rtrim(number_format($rate * 100, 2, ',', ''), '0'), ',').'%'],
            'rows' => $rows->push($total)->all(),
        ];
    }

    protected function movement(int $year): array
    {
        $rows = $this->dep->register($year)->groupBy(fn ($s) => $this->className($s))->sortKeys()
            ->map(fn (Collection $g, $class) => [$class, $g->sum('cost_open'), $g->sum('cost_add'), $g->sum('cost_disposed'), $g->sum('cost_close'),
                $g->sum('commercial.accum_open'), $g->sum('commercial.depreciation'), $g->sum('commercial.accum_disposed'), $g->sum('commercial.accum_close'),
                $g->sum('commercial.nbv_close')])->values();
        $total = ['Jumlah'];
        foreach (range(1, 9) as $i) {
            $total[] = round($rows->sum($i), 2);
        }

        return [
            'headers' => ['Kelas', 'Biaya awal', 'Penambahan', 'Pelepasan', 'Biaya akhir', 'Akum. awal', 'Penyusutan', 'Akum. pelepasan', 'Akum. akhir', 'Nilai buku akhir'],
            'rows' => $rows->push($total)->all(),
        ];
    }

    protected function capitalisation(): array
    {
        $policy = $this->dep->policy();
        $assets = Asset::query()->whereHas('financial', fn ($f) => $f->whereNotNull('acquisition_cost'))
            ->whereIn('lifecycle_status', ['draft', 'active'])->with('financial', 'category')->orderBy('asset_code')->get();
        $rows = [];
        foreach ($assets as $a) {
            if ($issue = $this->dep->capitalisationIssue($a)) {
                $rows[] = [$a->asset_code, $a->name, $a->depreciationClass()?->code, \App\Models\AssetFinancial::CAPITALISATION[$a->financial->capitalisation] ?? $a->financial->capitalisation,
                    (float) $a->financial->acquisition_cost, $a->financial->group_reference, $issue];
            }
        }
        // kapitalisasi kelompok: total per referensi harus >= batas pembelian kelompok
        $assets->filter(fn ($a) => $a->financial->capitalisation === 'group' && filled($a->financial->group_reference))
            ->groupBy('financial.group_reference')->each(function ($g, $ref) use (&$rows, $policy) {
                $sum = $g->sum(fn ($a) => (float) $a->financial->acquisition_cost);
                if ($sum < (float) $policy->group_purchase_threshold) {
                    $rows[] = ['Kelompok', $ref.' ('.$g->count().' aset)', '', 'Kelompok', $sum, $ref,
                        'Total kelompok di bawah batas '.\App\Support\Fmt::rp($policy->group_purchase_threshold)];
                }
            });

        return ['headers' => ['Kode', 'Nama', 'Kelas', 'Perlakuan', 'Biaya perolehan', 'Ref. kelompok', 'Catatan'], 'rows' => $rows];
    }

    protected function missing(): array
    {
        return [
            'headers' => ['Kode', 'Nama', 'Lokasi', 'Kelas', 'Dokumen wajib belum ada', 'Dokumen wajib kedaluwarsa'],
            'rows' => $this->docs->missingDocuments()->map(fn ($r) => [$r['asset']->asset_code, $r['asset']->name, $r['asset']->location?->full_path, $r['class'],
                implode('; ', $r['missing']), implode('; ', $r['expired'])])->all(),
        ];
    }

    protected function expiring(?User $u): array
    {
        $today = CarbonImmutable::now(\App\Support\Fmt::tz())->startOfDay();
        $q = AssetDocument::query()->with('type', 'pic', 'assets')->whereIn('status', ['expiring', 'expired', 'renewing'])->orderBy('valid_until');
        if ($u) {
            $q->visibleTo($u);
        }

        return [
            'headers' => ['No. register', 'Jenis', 'Judul', 'Nomor dokumen', 'Berlaku s.d.', 'Sisa hari', 'Status', 'PIC', 'Aset', 'Perpanjangan diajukan'],
            'rows' => $q->get()->map(fn ($d) => [$d->register_no, $d->type?->code, $d->title, $d->document_number, $d->valid_until?->format('d/m/Y'), $d->daysLeft($today),
                AssetDocument::STATUSES[$d->status] ?? $d->status, $d->pic?->name, $d->assets->pluck('asset_code')->join(', '), $d->renewal_submitted_at?->format('d/m/Y')])->all(),
        ];
    }

    protected function uninsured(): array
    {
        $threshold = (float) $this->dep->policy()->capitalisation_threshold;
        $rows = Asset::query()->whereIn('lifecycle_status', ['draft', 'active'])
            ->whereHas('financial', fn ($f) => $f->where('acquisition_cost', '>=', $threshold))
            ->whereDoesntHave('documents', fn ($d) => $d->whereIn('asset_documents.status', AssetDocument::LIVE)->whereHas('type', fn ($t) => $t->where('is_insurance', true)))
            ->with('financial', 'category', 'location')->orderBy('asset_code')->get()
            ->map(fn ($a) => [$a->asset_code, $a->name, $a->category?->name, $a->location?->full_path, (float) $a->financial->acquisition_cost])->all();

        return ['headers' => ['Kode', 'Nama', 'Kategori', 'Lokasi', 'Biaya perolehan'], 'rows' => $rows];
    }
}
