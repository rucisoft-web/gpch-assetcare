@php
    use App\Support\Fmt;
    $svc = app(\App\Services\DepreciationService::class);
    $p = $svc->params($asset);
    $year = (int) now(Fmt::tz())->year;
    $rows = $p ? $svc->schedule($asset) : collect();
    $now = $p ? $svc->yearSummary($asset, $year, $p) : null;
    $issue = $svc->capitalisationIssue($asset->loadMissing('financial', 'category'));
    $f = $asset->financial;
@endphp
<div class="arah">
@if (! $f || $f->acquisition_cost === null)
    <div class="arah-empty">Biaya perolehan belum diisi. Lengkapi tab Keuangan untuk menghitung penyusutan.</div>
@elseif ($f->capitalisation === 'expensed')
    <div class="arah-empty">Dibebankan (di bawah batas kapitalisasi); tidak disusutkan.</div>
@else
    @if ($issue)<p style="color:var(--k-kuning); margin:0 0 .6rem">⚠ {{ $issue }}</p>@endif
    <div class="arah-grid arah-grid-4">
        <div class="arah-stat"><span class="label">Kelas</span><span class="value" style="font-size:1.1rem">{{ $p['class']?->code ?? 'belum' }}</span>
            <span class="sub">{{ $p['class']?->name ?? 'Atur kelas di kategori aset' }}</span></div>
        <div class="arah-stat"><span class="label">Komersial</span>
            <span class="value" style="font-size:1.1rem">{{ $p['commercial_life'] ? round($p['commercial_life'] / 12, 1).' th' : 'Tidak disusutkan' }}</span>
            <span class="sub">mulai {{ $p['commercial_start']?->translatedFormat('M Y') ?? '-' }} · {{ Fmt::rp($svc->monthly($p, 'commercial')) }}/bln · residu {{ Fmt::rp($p['residual']) }}</span></div>
        <div class="arah-stat"><span class="label">Fiskal</span>
            <span class="value" style="font-size:1.1rem">{{ $p['fiscal_group'] ?? '-' }} · {{ $p['fiscal_life'] ? round($p['fiscal_life'] / 12, 1).' th' : 'tidak disusutkan' }}</span>
            <span class="sub">mulai {{ $p['fiscal_start']?->translatedFormat('M Y') ?? '-' }} · {{ Fmt::rp($svc->monthly($p, 'fiscal')) }}/bln</span></div>
        <div class="arah-stat"><span class="label">Nilai buku akhir {{ $year }}</span>
            <span class="value" style="font-size:1.1rem">{{ $now ? Fmt::rp($now['commercial']['nbv_close']) : '-' }}</span>
            <span class="sub">fiskal {{ $now ? Fmt::rp($now['fiscal']['nbv_close']) : '-' }}</span></div>
    </div>
    @if ($rows->isNotEmpty())
    <div class="arah-scroll" style="max-height:22rem; margin-top:.8rem">
        <table class="arah-table">
            <thead><tr><th>Tahun</th><th style="text-align:right">Penyusutan komersial</th><th style="text-align:right">Nilai buku komersial</th><th style="text-align:right">Penyusutan fiskal</th><th style="text-align:right">Nilai sisa fiskal</th><th style="text-align:right">Koreksi fiskal</th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr @if ($r['year'] === $year) style="font-weight:600" @endif>
                    <td>{{ $r['year'] }}</td>
                    <td style="text-align:right">{{ Fmt::rp($r['commercial']['depreciation']) }}</td>
                    <td style="text-align:right">{{ Fmt::rp($r['commercial']['nbv_close']) }}</td>
                    <td style="text-align:right">{{ Fmt::rp($r['fiscal']['depreciation']) }}</td>
                    <td style="text-align:right">{{ Fmt::rp($r['fiscal']['nbv_close']) }}</td>
                    <td style="text-align:right">{{ $r['correction'] == 0 ? '-' : ($r['correction'] < 0 ? 'negatif ' : 'positif ').Fmt::rp(abs($r['correction'])) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
@endif
</div>
