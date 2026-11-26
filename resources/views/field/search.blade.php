@extends('field.layout')
@section('title', 'Cari')
@section('heading', 'Cari aset atau kamar')
@section('content')
<form method="get" action="{{ route('field.search') }}" class="card">
    <input type="search" name="q" value="{{ $q }}" placeholder="mis. 801, AC, MB-08-819-TV-01" autofocus>
    <button class="btn" style="margin-top:10px">Cari</button>
</form>
@if ($rooms->isNotEmpty())
<div class="card"><b>Lokasi / kamar</b>
    @foreach ($rooms as $l)<a class="row" href="{{ route('field.location', $l) }}"><span class="t"><b>{{ $l->name }}</b><span class="muted">{{ $l->full_path }}</span></span><span class="chev">›</span></a>@endforeach
</div>
@endif
@if ($assets->isNotEmpty())
<div class="card"><b>Aset</b>
    @foreach ($assets as $a)<a class="row" href="{{ route('field.asset', $a) }}"><span class="t"><b>{{ $a->name }}</b><span class="muted">{{ $a->asset_code }} · {{ $a->location?->name }}</span></span>@include('field._status')</a>@endforeach
</div>
@endif
@if (mb_strlen($q) >= 2 && $rooms->isEmpty() && $assets->isEmpty())<div class="card muted">Tidak ada hasil untuk "{{ $q }}".</div>@endif
@endsection
