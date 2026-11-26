<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\DepreciationClass;
use App\Models\FiscalGroup;
use App\Models\FixedAssetPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Jadwal penyusutan dua buku (Kebijakan Penyusutan GPCH bagian 2 & 5):
 *  - komersial SAK EP: garis lurus, mulai bulan aset siap dipakai, bulan pelepasan tidak disusutkan,
 *    residu sesuai kelas (kendaraan 10%), saldo awal migrasi disusutkan prospektif atas sisa umur;
 *  - fiskal PMK 72/2023: garis lurus per kelompok, mulai bulan pengeluaran, tanpa residu.
 * Hanya perhitungan & laporan; jurnal tetap dibuat di sistem akuntansi.
 */
class DepreciationService
{
    public const BOOKS = ['commercial' => 'Komersial (SAK EP)', 'fiscal' => 'Fiskal (PMK 72/2023)'];

    protected ?FixedAssetPolicy $policy = null;

    /** @var array<int, DepreciationClass|null> */
    protected array $classes = [];

    public function policy(): FixedAssetPolicy
    {
        return $this->policy ??= FixedAssetPolicy::current();
    }

    public function setPolicy(FixedAssetPolicy $policy): static
    {
        $this->policy = $policy;

        return $this;
    }

    protected function classById(?int $id): ?DepreciationClass
    {
        if (! $id) {
            return null;
        }

        return $this->classes[$id] ??= DepreciationClass::withoutGlobalScopes()->find($id);
    }

    /**
     * Parameter penyusutan aset (null = tidak ada biaya perolehan / dibebankan).
     *
     * @return array<string, mixed>|null
     */
    public function params(Asset $a): ?array
    {
        $a->loadMissing('financial', 'category');
        $f = $a->financial;
        if (! $f || $f->acquisition_cost === null || $f->capitalisation === 'expensed') {
            return null;
        }
        $class = $this->classById($f->depreciation_class_id ?? $a->category?->depreciation_class_id);
        $cost = (float) $f->acquisition_cost;
        $depreciable = $class ? ($class->is_depreciable && $class->commercial_life_months) : false;
        $acquired = $f->acquisition_date ?? $a->purchase_date ?? $f->depreciation_start_date ?? $a->in_service_date;
        $commStart = $f->depreciation_start_date ?? $a->in_service_date ?? $f->acquisition_date ?? $a->purchase_date;
        $fiscalGroup = $f->fiscal_group_code ?? $a->category?->fiscal_group_override ?? $class?->fiscal_group_code;
        $fiscalLife = $f->fiscal_life_months ?? FiscalGroup::lifeFor($fiscalGroup, $this->policy());

        return [
            'class' => $class,
            'cost' => $cost,
            'acquired' => $acquired ? CarbonImmutable::parse($acquired->toDateString()) : null,
            'residual' => $f->residual_value !== null ? (float) $f->residual_value : round($cost * (float) ($class?->residual_pct ?? 0) / 100, 2),
            'commercial_life' => $depreciable ? (int) ($f->commercial_life_months ?? $class->commercial_life_months) : null,
            'commercial_start' => $commStart ? CarbonImmutable::parse($commStart->toDateString())->startOfMonth() : null,
            'fiscal_group' => $fiscalGroup,
            'fiscal_life' => $fiscalLife,
            'fiscal_start' => ($fs = $f->fiscal_start_date ?? $f->acquisition_date ?? $a->purchase_date ?? $commStart) ? CarbonImmutable::parse($fs->toDateString())->startOfMonth() : null,
            'opening_date' => $f->opening_balance_date ? CarbonImmutable::parse($f->opening_balance_date->toDateString())->startOfMonth() : null,
            'opening_commercial' => $f->opening_accum_commercial !== null ? (float) $f->opening_accum_commercial : null,
            'opening_fiscal' => $f->opening_accum_fiscal !== null ? (float) $f->opening_accum_fiscal : null,
            'impairment' => (float) ($f->impairment_loss ?? 0),
            'disposal' => $f->disposal_date ? CarbonImmutable::parse($f->disposal_date->toDateString())->startOfMonth() : null,
            'capitalisation' => $f->capitalisation,
        ];
    }

    /** Jumlah bulan inklusif dari bulan $from s.d. bulan $to (0 bila $to sebelum $from). */
    public static function months(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return max(0, ($to->year * 12 + $to->month) - ($from->year * 12 + $from->month) + 1);
    }

    /** Akumulasi penyusutan per akhir bulan $asOf. */
    public function accumulated(array $p, string $book, CarbonImmutable $asOf): float
    {
        $fiscal = $book === 'fiscal';
        $life = $fiscal ? $p['fiscal_life'] : $p['commercial_life'];
        $start = $fiscal ? $p['fiscal_start'] : $p['commercial_start'];
        if (! $life || ! $start) {
            return 0.0;
        }
        $base = $p['cost'] - ($fiscal ? 0.0 : $p['residual']);
        $end = $asOf->startOfMonth();
        if ($p['disposal'] && $p['disposal']->lte($end)) {
            $end = $p['disposal']->subMonth(); // bulan pelepasan tidak disusutkan
        }
        $opening = $fiscal ? $p['opening_fiscal'] : $p['opening_commercial'];

        if ($opening !== null && $p['opening_date'] && $end->gte($p['opening_date'])) {
            $elapsed = min($life, self::months($start, $p['opening_date']));
            $remainingMonths = $life - $elapsed;
            $remainingBase = max(0.0, $base - $opening);
            $after = self::months($p['opening_date']->addMonth(), $end);
            $accum = $opening + ($remainingMonths > 0 ? $remainingBase * min($after, $remainingMonths) / $remainingMonths : 0.0);

            return round(min($accum, max($base, $opening)), 2);
        }

        $n = min($life, self::months($start, $end));

        return round($base * $n / $life, 2);
    }

    /** Penyusutan per bulan (penuh) saat ini, untuk kartu aset. */
    public function monthly(array $p, string $book): float
    {
        $life = $book === 'fiscal' ? $p['fiscal_life'] : $p['commercial_life'];
        if (! $life) {
            return 0.0;
        }

        return round(($p['cost'] - ($book === 'fiscal' ? 0.0 : $p['residual'])) / $life, 2);
    }

    /**
     * Ringkasan satu tahun buku untuk satu aset, kedua buku.
     *
     * @return array<string, mixed>|null
     */
    public function yearSummary(Asset $a, int $year, ?array $p = null): ?array
    {
        $p ??= $this->params($a);
        if (! $p || ! $p['acquired']) {
            return null;
        }
        $yStart = CarbonImmutable::create($year, 1, 1);
        $yEnd = CarbonImmutable::create($year, 12, 1);
        $prevEnd = $yStart->subMonth();
        if ($p['acquired']->startOfMonth()->gt($yEnd)) {
            return null; // belum diperoleh
        }
        $disposedBefore = $p['disposal'] && $p['disposal']->lt($yStart);
        if ($disposedBefore) {
            return null;
        }
        $inOpening = $p['acquired']->startOfMonth()->lte($prevEnd);
        $disposedInYear = $p['disposal'] && $p['disposal']->lte($yEnd);
        $out = [
            'asset' => $a,
            'params' => $p,
            'cost_open' => $inOpening ? $p['cost'] : 0.0,
            'cost_add' => $inOpening ? 0.0 : $p['cost'],
            'cost_disposed' => $disposedInYear ? $p['cost'] : 0.0,
            'cost_close' => $disposedInYear ? 0.0 : $p['cost'],
            'disposed_in_year' => $disposedInYear,
        ];
        foreach (['commercial', 'fiscal'] as $book) {
            $open = $inOpening ? $this->accumulated($p, $book, $prevEnd) : 0.0;
            $atClose = $this->accumulated($p, $book, $yEnd);
            $dep = round($atClose - $open, 2);
            $out[$book] = [
                'accum_open' => $open,
                'depreciation' => $dep,
                'accum_disposed' => $disposedInYear ? $atClose : 0.0,
                'accum_close' => $disposedInYear ? 0.0 : $atClose,
                'nbv_close' => $disposedInYear ? 0.0 : round($p['cost'] - $atClose - ($book === 'commercial' ? $p['impairment'] : 0.0), 2),
            ];
        }
        $out['correction'] = round($out['commercial']['depreciation'] - $out['fiscal']['depreciation'], 2); // + koreksi positif, - koreksi negatif
        $out['temporary_difference'] = round($out['commercial']['nbv_close'] - $out['fiscal']['nbv_close'], 2);

        return $out;
    }

    /** Jadwal tahunan sepanjang umur (kartu aset). */
    public function schedule(Asset $a): Collection
    {
        $p = $this->params($a);
        if (! $p || ! $p['acquired']) {
            return collect();
        }
        $first = $p['acquired']->year;
        $lastMonths = max((int) $p['commercial_life'], (int) $p['fiscal_life']);
        $last = $p['disposal']?->year ?? max($first, ($p['commercial_start'] ?? $p['acquired'])->addMonths(max(1, $lastMonths))->year);
        $rows = collect();
        for ($y = $first; $y <= min($last, $first + 60); $y++) {
            if ($s = $this->yearSummary($a, $y, $p)) {
                $rows->push(['year' => $y] + $s);
            }
        }

        return $rows;
    }

    /**
     * Ringkasan semua aset untuk satu tahun buku.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function register(int $year): Collection
    {
        return Asset::query()->whereHas('financial', fn ($f) => $f->whereNotNull('acquisition_cost'))
            ->with('financial', 'category', 'location')->orderBy('asset_code')->get()
            ->map(fn (Asset $a) => $this->yearSummary($a, $year))->filter()->values();
    }

    /** Penilaian batas kapitalisasi (bagian 2.3); hanya peringatan, keputusan tetap di FC. */
    public function capitalisationIssue(Asset $a): ?string
    {
        $f = $a->financial;
        if (! $f || $f->acquisition_cost === null) {
            return null;
        }
        $policy = $this->policy();
        $class = $this->classById($f->depreciation_class_id ?? $a->category?->depreciation_class_id);
        $cost = (float) $f->acquisition_cost;
        if ($f->capitalisation === 'pending') {
            return 'Menunggu keputusan FC';
        }
        if ($f->capitalisation === 'capitalised' && $class?->code === 'BLD-R' && $cost < (float) $policy->renovation_threshold) {
            return 'Renovasi di bawah batas proyek '.\App\Support\Fmt::rp($policy->renovation_threshold);
        }
        if ($f->capitalisation === 'capitalised' && $class?->code !== 'BLD-R' && ! in_array($class?->code, ['LND', 'CIP'], true) && $cost < (float) $policy->capitalisation_threshold) {
            return 'Di bawah batas kapitalisasi '.\App\Support\Fmt::rp($policy->capitalisation_threshold).' per unit; tandai kelompok atau dibebankan';
        }
        if ($f->capitalisation === 'group' && blank($f->group_reference)) {
            return 'Kapitalisasi kelompok tanpa referensi pembelian / proyek';
        }

        return null;
    }
}
