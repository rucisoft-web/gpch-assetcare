@php use App\Support\Fmt; @endphp
<x-filament-panels::page>
<div class="arah">
    <div class="arah-card" style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:end; justify-content:space-between">
        <div style="display:flex; gap:.5rem; align-items:end; flex-wrap:wrap">
            <div><label class="arah-muted">Dari</label><input type="date" class="ac-input" wire:model.live="from"></div>
            <div><label class="arah-muted">Sampai</label><input type="date" class="ac-input" wire:model.live="to"></div>
        </div>
        <div style="display:flex; gap:.4rem">
            @if ($canExport)
                <a class="arah-btn arah-btn-primary" href="{{ route('reports.export', ['report' => $report, 'from' => $from, 'to' => $to]) }}">⤓ Unduh CSV</a>
            @endif
            <button type="button" class="arah-btn" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <div class="arah-grid arah-grid-4" style="margin-top:1rem">
        <div class="arah-stat"><span class="label">Respons sesuai SLA</span><span class="value">{{ Fmt::pct($kpi['sla_response']['value']) }}</span><span class="sub">{{ $kpi['sla_response']['note'] }}</span></div>
        <div class="arah-stat"><span class="label">MTTR bruto / bersih</span><span class="value">{{ $kpi['mttr']['value'] === null ? 'N/A' : Fmt::minutes($kpi['mttr']['value']) }}</span><span class="sub">{{ $kpi['mttr']['value'] === null ? $kpi['mttr']['note'] : 'bersih '.Fmt::minutes($kpi['mttr']['net']).' · '.$kpi['mttr']['note'] }}</span></div>
        <div class="arah-stat"><span class="label">Kegagalan berulang</span><span class="value">{{ $kpi['repeat_failures'] }}</span><span class="sub">aset & mode sama ≤ {{ config('assetcare.repeat_failure_days') }} hari</span></div>
        <div class="arah-stat"><span class="label">Downtime aset</span><span class="value">{{ Fmt::num($kpi['downtime_hours'], 1) }} jam</span><span class="sub">{{ Fmt::num($kpi['room_days'], 1) }} hari-kamar terblokir</span></div>
    </div>
    <div class="arah-grid arah-grid-4" style="margin-top:1rem">
        <div class="arah-stat"><span class="label">Backlog terbuka</span><span class="value">{{ $kpi['open_wo'] }}</span>
            <span class="sub">@foreach ($kpi['aging'] as $k => $v){{ $k }}: {{ $v }}@if(! $loop->last) · @endif @endforeach</span></div>
        <div class="arah-stat"><span class="label">Aset tidak beroperasi</span><span class="value">{{ $kpi['assets_out'] }}</span><span class="sub">{{ $kpi['assets_de'] }} kondisi D/E</span></div>
        <div class="arah-stat"><span class="label">PM tepat waktu</span><span class="value">N/A</span><span class="sub">{{ $kpi['pm_on_time']['note'] }}</span></div>
        @isset($kpi['cost'])
            <div class="arah-stat"><span class="label">Biaya pemeliharaan</span><span class="value">{{ Fmt::rp($kpi['cost']['provisional'] + $kpi['cost']['final']) }}</span><span class="sub">final {{ Fmt::rp($kpi['cost']['final']) }} · alokasi internal {{ Fmt::rp($kpi['cost']['internal']) }}</span></div>
        @else
            <div class="arah-stat"><span class="label">Label perlu tindakan</span><span class="value">{{ $kpi['labels_pending'] }}</span><span class="sub">belum terpasang / rusak / ulang</span></div>
        @endisset
    </div>

    <div class="arah-card" style="margin-top:1rem">
        <div style="display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.8rem">
            @foreach ($reports as $key => $label)
                <button type="button" wire:click="setReport('{{ $key }}')" class="arah-btn {{ $report === $key ? 'arah-btn-primary' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
        <h3 style="margin:0 0 .5rem">{{ $reports[$report] }} <span class="arah-muted">· {{ count($data['rows']) }} baris</span></h3>
        @if (in_array($report, ['penyusutan', 'fiskal', 'rekonsiliasi', 'mutasi'], true))
            <p class="arah-muted" style="margin:0 0 .5rem">Tahun buku {{ \Carbon\Carbon::parse($to)->year }} (mengikuti tanggal "Sampai"), posisi akhir Desember. Hanya perhitungan; jurnal tetap dibuat di sistem akuntansi.</p>
        @endif
        @if (empty($data['rows']))
            <div class="arah-empty">Tidak ada data pada periode / filter ini.</div>
        @else
            <div class="arah-scroll" style="max-height:34rem">
                <table class="arah-table">
                    <thead><tr>@foreach ($data['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach (array_slice($data['rows'], 0, 500) as $row)
                        <tr>@foreach ($row as $cell)<td>{{ is_float($cell) ? Fmt::num($cell, 2) : $cell }}</td>@endforeach</tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($data['rows']) > 500)<p class="arah-muted">Menampilkan 500 baris pertama. Unduh CSV untuk data lengkap.</p>@endif
        @endif
    </div>
</div>
</x-filament-panels::page>
