@extends('field.layout')
@section('title', 'Beranda')
@section('heading', 'Identifikasi Aset')
@section('sub', 'Halo, '.auth()->user()->name)
@section('content')
@php $pct = $total ? round($verified / $total * 100) : 0; @endphp
<div class="card">
    <div class="muted">Progres identifikasi seluruh aset</div>
    <div style="font-size:26px;font-weight:700">{{ $pct }}% <span class="muted" style="font-size:14px;font-weight:400">{{ number_format($verified, 0, ',', '.') }} dari {{ number_format($total, 0, ',', '.') }} aset</span></div>
    <div class="bar"><i style="width:{{ $pct }}%"></i></div>
    <div class="grid2" style="margin-top:12px">
        <div class="stat"><b>{{ $mineToday }}</b><span class="muted">saya hari ini</span></div>
        <div class="stat"><b style="color:var(--merah)">{{ $discrepancy }}</b><span class="muted">tidak ditemukan</span></div>
    </div>
</div>
<a class="big" href="{{ route('field.scan') }}"><span class="ic">⌗</span><span><b>Scan label QR</b><span class="muted">Arahkan kamera ke label aset</span></span></a>
<a class="big" href="{{ route('field.location') }}"><span class="ic">⌂</span><span><b>Telusuri lokasi / kamar</b><span class="muted">Pilih gedung › lantai › kamar, lalu cek asetnya satu per satu</span></span></a>
<a class="big" href="{{ route('field.search') }}"><span class="ic">⌕</span><span><b>Cari aset atau kamar</b><span class="muted">Kode aset, nama, nomor seri, nomor kamar</span></span></a>
<div class="card">
    <b>Gedung</b>
    @foreach ($buildings as $b)
        <a class="row" href="{{ route('field.location', $b['loc']) }}"><span class="t"><b>{{ $b['loc']->name }}</b><span class="muted">{{ $b['v'] }} / {{ $b['t'] }} aset sudah</span><div class="bar"><i style="width:{{ $b['t'] ? round($b['v'] / $b['t'] * 100) : 0 }}%"></i></div></span><span class="chev">›</span></a>
    @endforeach
</div>
@if ($recent->isNotEmpty())
<div class="card">
    <b>Terakhir saya identifikasi</b>
    @foreach ($recent as $a)
        <a class="row" href="{{ route('field.asset', $a) }}"><span class="t"><b>{{ $a->name }}</b><span class="muted">{{ $a->asset_code }} · {{ $a->location?->name }}</span></span>@include('field._status')</a>
    @endforeach
</div>
@endif
@endsection
