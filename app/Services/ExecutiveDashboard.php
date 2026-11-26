<?php

namespace App\Services;

use App\Filament\Resources\AssetDocuments\AssetDocumentResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDocument;
use App\Models\Department;
use App\Models\Location;
use App\Models\WoCostLine;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Data dashboard pimpinan: ringkasan kondisi aset, pemeliharaan, biaya & dokumen.
 * Setiap titik grafik membawa URL ke daftar terfilter, sehingga pimpinan dapat menelusuri sampai ke data rinci.
 */
class ExecutiveDashboard
{
    private const PALETTE = ['#006632', '#B8862B', '#34506E', '#A8432A', '#4F6B3A', '#7A4B8C', '#2F8A57', '#D9B76A', '#5E7E9C', '#C9774F', '#8A8F3A', '#6E5F52'];

    public function build(bool $financial): array
    {
        $today = CarbonImmutable::now(\App\Support\Fmt::tz())->startOfDay();
        $assets = Asset::query()->whereNotIn('lifecycle_status', ['disposed', 'lost']);
        $total = (clone $assets)->count();
        $verified = (clone $assets)->where('verification_status', 'verified')->count();
        $bad = (clone $assets)->whereIn('condition_grade', ['D', 'E'])->count();
        $down = (clone $assets)->whereIn('operational_status', ['out_of_service', 'under_repair'])->count();
        $critical = (clone $assets)->where('criticality', 'critical')->count();
        $open = WorkOrder::whereIn('status', WorkOrder::OPEN)->count();
        $overdue = WorkOrder::whereIn('status', WorkOrder::OPEN)->where('due_at', '<', now())->count();
        $docsDue = AssetDocument::whereIn('status', ['expiring', 'expired'])->count();
        $cost90 = $financial ? (float) WoCostLine::whereHas('workOrder', fn ($q) => $q->where('created_at', '>=', $today->subDays(90)))->sum(DB::raw('quantity * unit_cost')) : null;

        $woUrl = fn (array $filters = [], string $tab = 'semua') => WorkOrderResource::getUrl('index', ['tab' => $tab, 'filters' => $filters]);
        $assetUrl = fn (array $filters) => AssetResource::getUrl('index', ['filters' => $filters]);

        return [
            'generated' => now(),
            'kpi' => array_values(array_filter([
                ['label' => 'Aset terdaftar', 'value' => $total, 'sub' => number_format($critical, 0, ',', '.').' berkritikalitas kritis', 'url' => AssetResource::getUrl('index'), 'tone' => 'hijau'],
                ['label' => 'Terverifikasi fisik', 'value' => $total ? round($verified / $total * 100).'%' : '0%', 'sub' => number_format($total - $verified, 0, ',', '.').' belum diverifikasi', 'url' => $assetUrl(['verification_status' => ['value' => 'unverified']]), 'tone' => $total && $verified / $total >= .8 ? 'hijau' : 'kuning'],
                ['label' => 'Kondisi D/E', 'value' => $bad, 'sub' => 'perlu kajian penggantian', 'url' => $assetUrl(['condition_grade' => ['value' => 'D']]), 'tone' => $bad ? 'merah' : 'hijau'],
                ['label' => 'Tidak beroperasi / diperbaiki', 'value' => $down, 'sub' => 'aset sedang tidak tersedia', 'url' => $assetUrl(['operational_status' => ['value' => 'out_of_service']]), 'tone' => $down ? 'kuning' : 'hijau'],
                ['label' => 'Work order terbuka', 'value' => $open, 'sub' => $overdue.' melewati target SLA', 'url' => $woUrl([], 'terbuka'), 'tone' => $overdue ? 'merah' : 'hijau'],
                ['label' => 'Dokumen akan/sudah berakhir', 'value' => $docsDue, 'sub' => 'izin, polis, kontrak', 'url' => AssetDocumentResource::getUrl('index', ['filters' => ['status' => ['values' => ['expiring', 'expired']]]]), 'tone' => $docsDue ? 'kuning' : 'hijau'],
                $financial ? ['label' => 'Biaya pemeliharaan 90 hari', 'value' => 'Rp '.number_format($cost90 / 1e6, 1, ',', '.').' jt', 'sub' => 'material, vendor & tenaga', 'url' => $woUrl(['status' => ['values' => ['closed']]]), 'tone' => 'emas'] : null,
            ])),
            'charts' => array_values(array_filter([
                $this->conditionChart($assetUrl),
                $this->operationalChart($assetUrl),
                $this->categoryChart($assetUrl),
                $this->locationChart($assetUrl),
                $this->verificationByDept($assetUrl),
                $this->woTrend($today, $woUrl),
                $this->woByType($woUrl),
                $this->documentStatus(),
                $this->topFailing(),
                $financial ? $this->costByMonth($today, $woUrl) : null,
            ])),
        ];
    }

    private function chart(string $id, string $title, string $type, array $labels, array $datasets, array $urls, string $hint, array $opts = []): array
    {
        return ['id' => $id, 'title' => $title, 'type' => $type, 'labels' => array_values($labels), 'datasets' => $datasets, 'urls' => $urls, 'hint' => $hint, 'opts' => $opts];
    }

    private function conditionChart(callable $assetUrl): array
    {
        $rows = Asset::query()->whereNotIn('lifecycle_status', ['disposed', 'lost'])->select('condition_grade', DB::raw('count(*) n'))->groupBy('condition_grade')->pluck('n', 'condition_grade');
        $labels = $vals = $urls = [];
        foreach (Asset::CONDITIONS as $k => $label) {
            $labels[] = $label;
            $vals[] = (int) ($rows[$k] ?? 0);
            $urls[] = $assetUrl(['condition_grade' => ['value' => $k]]);
        }
        if ($n = (int) ($rows[''] ?? $rows[null] ?? 0)) {
            $labels[] = 'Belum dinilai';
            $vals[] = $n;
            $urls[] = AssetResource::getUrl('index');
        }

        return $this->chart('kondisi', 'Kondisi fisik aset', 'doughnut', $labels, [['data' => $vals, 'backgroundColor' => ['#2F7A3F', '#8BAE5A', '#D9B76A', '#C9774F', '#A8432A', '#BDB3A4']]], $urls, 'Klik irisan untuk melihat daftar aset dengan kondisi tersebut.');
    }

    private function operationalChart(callable $assetUrl): array
    {
        $rows = Asset::query()->whereNotIn('lifecycle_status', ['disposed', 'lost'])->select('operational_status', DB::raw('count(*) n'))->groupBy('operational_status')->pluck('n', 'operational_status');
        $labels = $vals = $urls = [];
        foreach (Asset::OPERATIONAL as $k => $label) {
            $labels[] = $label;
            $vals[] = (int) ($rows[$k] ?? 0);
            $urls[] = $assetUrl(['operational_status' => ['value' => $k]]);
        }

        return $this->chart('operasional', 'Status operasional', 'doughnut', $labels, [['data' => $vals, 'backgroundColor' => ['#2F7A3F', '#D9B76A', '#34506E', '#A8432A']]], $urls, 'Aset menurun atau tidak beroperasi berisiko mengganggu layanan tamu.');
    }

    private function categoryChart(callable $assetUrl): array
    {
        $rows = Asset::query()->whereNotIn('lifecycle_status', ['disposed', 'lost'])->select('category_id', DB::raw('count(*) n'))->groupBy('category_id')->orderByDesc('n')->limit(12)->get();
        $names = AssetCategory::whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');

        return $this->chart('kategori', 'Aset per kategori (12 terbanyak)', 'bar', $rows->map(fn ($r) => $names[$r->category_id] ?? '-')->all(),
            [['label' => 'Aset', 'data' => $rows->pluck('n')->map(fn ($v) => (int) $v)->all(), 'backgroundColor' => '#006632']],
            $rows->map(fn ($r) => $assetUrl(['category_id' => ['value' => $r->category_id]]))->all(), 'Klik batang untuk membuka daftar aset kategori itu.', ['indexAxis' => 'y']);
    }

    private function locationChart(callable $assetUrl): array
    {
        // kelompokkan ke tingkat lantai (atau tingkat kedua pohon lokasi)
        $locs = Location::query()->get(['id', 'parent_id', 'name', 'location_type', 'full_path'])->keyBy('id');
        $bucket = function ($id) use ($locs) {
            $chain = [];
            while ($id && ($l = $locs[$id] ?? null)) {
                array_unshift($chain, $l);
                $id = $l->parent_id;
            }
            foreach ($chain as $l) {
                if ($l->location_type === 'floor') {
                    return $l;
                }
            }

            return $chain[1] ?? $chain[0] ?? null;
        };
        $counts = [];
        foreach (Asset::query()->whereNotIn('lifecycle_status', ['disposed', 'lost'])->select('location_id', DB::raw('count(*) n'))->groupBy('location_id')->get() as $r) {
            if ($b = $bucket($r->location_id)) {
                $counts[$b->id] = ($counts[$b->id] ?? 0) + (int) $r->n;
            }
        }
        arsort($counts);
        $counts = array_slice($counts, 0, 12, true);

        return $this->chart('lokasi', 'Sebaran aset per gedung / lantai', 'bar', array_map(fn ($id) => $locs[$id]->full_path ?? $locs[$id]->name, array_keys($counts)),
            [['label' => 'Aset', 'data' => array_values($counts), 'backgroundColor' => '#34506E']],
            array_map(fn ($id) => $assetUrl(['location_id' => ['value' => $id]]), array_keys($counts)), 'Termasuk semua ruang di bawah lantai tersebut.', ['indexAxis' => 'y']);
    }

    private function verificationByDept(callable $assetUrl): array
    {
        $rows = Asset::query()->whereNotIn('lifecycle_status', ['disposed', 'lost'])->whereNotNull('department_id')
            ->select('department_id', DB::raw("sum(verification_status = 'verified') v"), DB::raw("sum(verification_status <> 'verified') u"))
            ->groupBy('department_id')->orderByDesc(DB::raw('count(*)'))->limit(12)->get();
        $names = Department::whereIn('id', $rows->pluck('department_id'))->pluck('name', 'id');
        $urlsV = $rows->map(fn ($r) => $assetUrl(['department_id' => ['value' => $r->department_id], 'verification_status' => ['value' => 'verified']]))->all();
        $urlsU = $rows->map(fn ($r) => $assetUrl(['department_id' => ['value' => $r->department_id], 'verification_status' => ['value' => 'unverified']]))->all();

        return $this->chart('verifikasi', 'Progres verifikasi fisik per departemen', 'bar', $rows->map(fn ($r) => $names[$r->department_id] ?? '-')->all(), [
            ['label' => 'Terverifikasi', 'data' => $rows->pluck('v')->map(fn ($v) => (int) $v)->all(), 'backgroundColor' => '#2F7A3F'],
            ['label' => 'Belum', 'data' => $rows->pluck('u')->map(fn ($v) => (int) $v)->all(), 'backgroundColor' => '#D9B76A'],
        ], ['byDataset' => [$urlsV, $urlsU]], 'Target implementasi: semua aset terverifikasi & berlabel.', ['stacked' => true, 'indexAxis' => 'y']);
    }

    private function woTrend(CarbonImmutable $today, callable $woUrl): array
    {
        $start = $today->startOfWeek()->subWeeks(11);
        $labels = $created = $done = $urls = [];
        for ($w = 0; $w < 12; $w++) {
            $a = $start->addWeeks($w);
            $b = $a->addDays(6);
            $labels[] = $a->format('d M');
            $created[] = WorkOrder::whereBetween('received_at', [$a->setTimezone('UTC'), $b->endOfDay()->setTimezone('UTC')])->count();
            $done[] = WorkOrder::whereBetween('completed_at', [$a->setTimezone('UTC'), $b->endOfDay()->setTimezone('UTC')])->count();
            $urls[] = $woUrl(['dibuat' => ['from' => $a->toDateString(), 'until' => $b->toDateString()]]);
        }

        return $this->chart('tren', 'Tren work order 12 minggu', 'line', $labels, [
            ['label' => 'Masuk', 'data' => $created, 'borderColor' => '#A8432A', 'backgroundColor' => 'rgba(168,67,42,.15)', 'tension' => .3, 'fill' => true],
            ['label' => 'Diselesaikan', 'data' => $done, 'borderColor' => '#006632', 'backgroundColor' => 'rgba(0,102,50,.1)', 'tension' => .3, 'fill' => true],
        ], $urls, 'Garis "masuk" di atas "diselesaikan" terus-menerus berarti antrean menumpuk. Klik titik untuk daftar WO minggu itu.');
    }

    private function woByType(callable $woUrl): array
    {
        $rows = WorkOrder::where('received_at', '>=', now()->subDays(90))->select('type', DB::raw('count(*) n'))->groupBy('type')->pluck('n', 'type');
        $labels = $vals = $urls = [];
        foreach (WorkOrder::TYPES as $k => $label) {
            if (! empty($rows[$k])) {
                $labels[] = $label;
                $vals[] = (int) $rows[$k];
                $urls[] = $woUrl(['type' => ['values' => [$k]]]);
            }
        }

        return $this->chart('jenis', 'Jenis pekerjaan (90 hari)', 'doughnut', $labels, [['data' => $vals, 'backgroundColor' => self::PALETTE]], $urls,
            'Porsi korektif/darurat yang besar dibanding preventive menandakan pemeliharaan masih reaktif.');
    }

    private function topFailing(): array
    {
        $rows = WorkOrder::whereNotNull('asset_id')->whereIn('type', ['corrective', 'emergency'])->where('received_at', '>=', now()->subDays(365))
            ->select('asset_id', DB::raw('count(*) n'))->groupBy('asset_id')->orderByDesc('n')->limit(10)->get();
        $assets = Asset::whereIn('id', $rows->pluck('asset_id'))->get()->keyBy('id');

        return $this->chart('sering', 'Aset paling sering rusak (12 bulan)', 'bar', $rows->map(fn ($r) => ($a = $assets[$r->asset_id] ?? null) ? $a->asset_code.' · '.$a->name : '-')->all(),
            [['label' => 'Perbaikan', 'data' => $rows->pluck('n')->map(fn ($v) => (int) $v)->all(), 'backgroundColor' => '#A8432A']],
            $rows->map(fn ($r) => AssetResource::getUrl('view', ['record' => $r->asset_id]))->all(), 'Kandidat penggantian atau kontrak servis. Klik untuk membuka kartu aset.', ['indexAxis' => 'y']);
    }

    private function costByMonth(CarbonImmutable $today, callable $woUrl): array
    {
        $labels = $mat = $ven = $lab = $urls = [];
        for ($m = 5; $m >= 0; $m--) {
            $a = $today->startOfMonth()->subMonths($m);
            $b = $a->endOfMonth();
            $labels[] = $a->translatedFormat('M Y');
            $q = fn ($type) => (float) WoCostLine::where('type', $type)->whereHas('workOrder', fn ($w) => $w->whereBetween('received_at', [$a->setTimezone('UTC'), $b->setTimezone('UTC')]))->sum(DB::raw('quantity * unit_cost'));
            $mat[] = $q('material');
            $ven[] = $q('vendor');
            $lab[] = $q('labor') + $q('other');
            $urls[] = $woUrl(['dibuat' => ['from' => $a->toDateString(), 'until' => $b->toDateString()]]);
        }

        return $this->chart('biaya', 'Biaya pemeliharaan per bulan (Rp)', 'bar', $labels, [
            ['label' => 'Material', 'data' => $mat, 'backgroundColor' => '#B8862B'],
            ['label' => 'Jasa vendor', 'data' => $ven, 'backgroundColor' => '#34506E'],
            ['label' => 'Tenaga & lain', 'data' => $lab, 'backgroundColor' => '#4F6B3A'],
        ], $urls, 'Klik batang untuk WO bulan tersebut.', ['stacked' => true, 'money' => true]);
    }

    private function documentStatus(): array
    {
        $rows = AssetDocument::select('status', DB::raw('count(*) n'))->groupBy('status')->pluck('n', 'status');
        $labels = $vals = $urls = [];
        foreach (AssetDocument::STATUSES as $k => $label) {
            if (! empty($rows[$k])) {
                $labels[] = $label;
                $vals[] = (int) $rows[$k];
                $urls[] = AssetDocumentResource::getUrl('index', ['filters' => ['status' => ['values' => [$k]]]]);
            }
        }

        return $this->chart('dokumen', 'Status dokumen legalitas', 'doughnut', $labels, [['data' => $vals, 'backgroundColor' => ['#2F7A3F', '#D9B76A', '#A8432A', '#34506E', '#BDB3A4', '#6E5F52', '#8A8F3A']]], $urls,
            'Dokumen kedaluwarsa dapat berarti aset tidak boleh dioperasikan (riksa uji K3) atau tidak tertanggung.');
    }
}
