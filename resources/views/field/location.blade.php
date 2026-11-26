@extends('field.layout')
@section('title', $location?->name ?? 'Lokasi')
@section('heading', $location?->name ?? 'Pilih lokasi')
@if ($location)
@section('sub', collect($trail)->slice(0, -1)->pluck('name')->join(' › '))
@section('back', $location->parent_id ? route('field.location', $location->parent_id) : route('field.location'))
@else
@section('back', route('field.home'))
@endif
@section('content')
@if ($stat)
<div class="card">
    <div class="muted">{{ $stat['v'] }} dari {{ $stat['t'] }} aset sudah diidentifikasi</div>
    <div class="bar"><i style="width:{{ $stat['t'] ? round($stat['v'] / $stat['t'] * 100) : 0 }}%"></i></div>
</div>
@endif

@if ($children->isNotEmpty())
<div class="card">
    <b>{{ $location ? 'Di dalam '.$location->name : 'Gedung & area' }}</b>
    @foreach ($children as $ch)
        <a class="row" href="{{ route('field.location', $ch['loc']) }}">
            <span class="t"><b>{{ $ch['loc']->name }}</b><span class="muted">{{ $ch['t'] ? $ch['v'].' / '.$ch['t'].' aset sudah' : 'belum ada aset' }}</span>
                @if ($ch['t'])<div class="bar"><i style="width:{{ round($ch['v'] / $ch['t'] * 100) }}%"></i></div>@endif</span>
            @if ($ch['t'] && $ch['v'] === $ch['t'])<span class="pill p-ok">Selesai</span>@endif
            <span class="chev">›</span>
        </a>
    @endforeach
</div>
@endif

@if ($location)
    @if ($assets->isNotEmpty() || $filter === 'belum')
    <div class="tabs">
        <a href="{{ route('field.location', $location) }}" class="{{ $filter !== 'belum' ? 'on' : '' }}">Semua</a>
        <a href="{{ route('field.location', [$location, 'f' => 'belum']) }}" class="{{ $filter === 'belum' ? 'on' : '' }}">Belum diidentifikasi</a>
    </div>
    @endif
    @if ($assets->isNotEmpty())
    <div class="card">
        <b>Aset di {{ $location->name }}</b>
        @foreach ($assets as $a)
            <a class="row" href="{{ route('field.asset', $a) }}"><span class="t"><b>{{ $a->name }}</b><span class="muted">{{ $a->asset_code }} · {{ $a->category?->name }}</span></span>@include('field._status')<span class="chev">›</span></a>
        @endforeach
    </div>
    @elseif ($filter === 'belum')
    <div class="card muted">Semua aset di lokasi ini sudah diidentifikasi.</div>
    @endif
    @if ($groups->isNotEmpty())
    <div class="card">
        <b>Barang dihitung jumlahnya</b>
        @foreach ($groups as $g)
            <a class="row" href="{{ route('field.group', $g) }}"><span class="t"><b>{{ $g->name }}</b><span class="muted">Standar {{ $g->expected_qty !== null ? (float) $g->expected_qty : '—' }} · tercatat {{ $g->on_hand !== null ? (float) $g->on_hand : 'belum dihitung' }} {{ $g->unit }}</span></span><span class="chev">›</span></a>
        @endforeach
    </div>
    @endif
    @if ($assets->isNotEmpty() || $groups->isNotEmpty())
    <a class="btn" href="{{ route('field.install', $location) }}" style="margin-bottom:10px">Pasang label QR di {{ $location->name }}</a>
    @endif
    @if (in_array($location->location_type, ['room', 'area', 'venue', 'point']) || $assets->isNotEmpty())
    <a class="btn alt" href="{{ route('field.new', $location) }}">+ Ada aset yang belum terdaftar</a>
    @endif
@endif
@endsection
