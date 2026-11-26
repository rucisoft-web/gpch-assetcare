@extends('field.layout')
@section('title', 'Pasang label · '.$location->name)
@section('heading', 'Pasang label · '.$location->name)
@section('sub', collect($trail)->slice(0, -1)->pluck('name')->join(' › '))
@section('back', route('field.location', $location))
@section('content')
@php $total = $items->count(); @endphp
<div class="card">
    <div class="muted"><b style="color:var(--tinta)">{{ $done }} dari {{ $total }}</b> label sudah terpasang di ruang ini</div>
    <div class="bar"><i style="width:{{ $total ? round($done / $total * 100) : 0 }}%"></i></div>
    <p class="muted" style="margin:8px 0 0">Tempel label pada aset yang namanya sama dengan tulisan di label, lalu scan. Setelah scan, isi kondisi & foto aset.</p>
</div>
@include('field._scanner', ['ratio' => '4/3'])
<form class="card" method="post" action="{{ route('field.install.scan', $location) }}" id="fs">
    @csrf
    <label style="margin-top:0">Atau ketik kode label / aset</label>
    <input type="text" name="kode" id="kode" autocapitalize="characters">
    <button class="btn" style="margin-top:10px">Konfirmasi terpasang</button>
</form>

@if ($total)
<div class="card">
    <b>Label untuk ruang ini</b>
    @foreach ($items as $l)
        @php
            $t = $l->asset ?? $l->quantityGroup; $st = $l->currentInstallationStatus();
            $pill = match ($st) { 'installed' => 'p-ok', 'pending' => 'p-no', default => 'p-bad' };
            $url = $l->asset ? route('field.asset', [$l->asset, 'pasang' => 1]) : route('field.group', [$l->quantityGroup, 'pasang' => 1]);
        @endphp
        <div class="row" style="flex-wrap:wrap">
            <a class="t" href="{{ $url }}" style="color:var(--tinta)"><b>{{ $t->name }}</b><span class="muted">{{ $t->asset_code ?? $t->group_code }}@if ($l->asset) · {{ $l->asset->verification_status === 'verified' ? 'kondisi sudah dicek' : 'kondisi belum dicek' }}@endif</span></a>
            <span class="pill {{ $pill }}">{{ \App\Models\InstallationEvent::STATUSES[$st] ?? $st }}</span>
            @if ($st !== 'installed')
            <details style="width:100%;margin-top:6px"><summary class="muted" style="cursor:pointer">Label bermasalah?</summary>
                <form method="post" action="{{ route('field.install.mark', [$location, $l]) }}" style="display:flex;gap:8px;margin-top:6px">
                    @csrf
                    <select name="status" style="flex:1"><option value="damaged">Label rusak</option><option value="missing">Label hilang</option><option value="needs_relabel">Perlu cetak ulang</option></select>
                    <button class="btn alt" style="width:auto;padding:10px 14px">Catat</button>
                </form>
            </details>
            @endif
        </div>
    @endforeach
</div>
@else
<div class="card muted">Belum ada label untuk ruang ini. Minta ruang IT mencetak label lokasi ini dulu.</div>
@endif
<a class="btn alt" href="{{ route('field.new', $location) }}">+ Ada aset yang belum terdaftar</a>
@endsection
@push('scripts')
<script>window.fieldOnScan = (t) => { document.getElementById('kode').value = t; document.getElementById('fs').submit(); };</script>
@endpush
