@extends('field.layout')
@section('title', $group->name)
@section('heading', $group->name)
@section('sub', $group->group_code)
@section('back', $group->location_id ? route('field.location', $group->location_id) : route('field.home'))
@section('content')
<div class="card">
    <dl class="kv">
        <dt>Lokasi</dt><dd>{{ $group->location?->full_path ?? '—' }}</dd>
        <dt>Kategori</dt><dd>{{ $group->category?->name }}</dd>
        <dt>Standar</dt><dd>{{ $group->expected_qty !== null ? (float) $group->expected_qty.' '.$group->unit : '—' }}</dd>
        <dt>Hitung terakhir</dt><dd>{{ $group->on_hand !== null ? (float) $group->on_hand.' '.$group->unit : 'belum dihitung' }}</dd>
    </dl>
</div>
<form method="post" action="{{ route('field.group.count', $group) }}" class="card">
    @csrf
    @if (request()->boolean('pasang') || old('pasang'))<input type="hidden" name="pasang" value="1">@endif
    <label for="qty" style="margin-top:0">Jumlah aktual ({{ $group->unit }})</label>
    <input id="qty" type="number" name="qty" inputmode="numeric" min="0" step="1" value="{{ old('qty') }}" required>
    @error('qty')<div class="err-t">{{ $message }}</div>@enderror
    <label for="reason">Catatan (wajib bila berbeda dari standar)</label>
    <textarea id="reason" name="reason">{{ old('reason') }}</textarea>
    @error('reason')<div class="err-t">{{ $message }}</div>@enderror
    <button class="btn" style="margin-top:14px">Simpan hitungan</button>
</form>
@endsection
