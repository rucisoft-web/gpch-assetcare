@extends('field.layout')
@section('title', 'Aset temuan')
@section('heading', 'Aset belum terdaftar')
@section('sub', $location->name)
@section('back', route('field.location', $location))
@section('content')
<form method="post" action="{{ route('field.new.save', $location) }}" enctype="multipart/form-data" class="card">
    @csrf
    <p class="muted" style="margin-top:0">Catat aset yang ada di {{ $location->name }} tetapi belum ada di daftar. Aset masuk sebagai draft; Asset Controller akan meninjau & mencetak labelnya.</p>
    <label for="name">Nama aset</label><input id="name" type="text" name="name" value="{{ old('name') }}" required placeholder="mis. Kursi rias">
    @error('name')<div class="err-t">{{ $message }}</div>@enderror
    <label for="cat">Kategori</label>
    <select id="cat" name="category_id" required><option value="">— pilih —</option>@foreach ($categories as $id => $n)<option value="{{ $id }}" @selected((string) old('category_id') === (string) $id)>{{ $n }}</option>@endforeach</select>
    @error('category_id')<div class="err-t">{{ $message }}</div>@enderror
    <label for="brand">Merek</label><input id="brand" type="text" name="brand" value="{{ old('brand') }}">
    <div class="grid2">
        <div><label for="model">Model / tipe</label><input id="model" type="text" name="model" value="{{ old('model') }}"></div>
        <div><label for="sn">No. seri</label><input id="sn" type="text" name="serial_number" value="{{ old('serial_number') }}"></div>
    </div>
    <label>Kondisi</label>
    <div class="seg">@foreach (\App\Models\Asset::CONDITIONS as $k => $v)<input type="radio" name="condition_grade" id="c{{ $k }}" value="{{ $k }}" @checked(old('condition_grade', 'A') === $k)><label for="c{{ $k }}">{{ $k }}</label>@endforeach</div>
    <label for="op">Status operasional</label>
    <select id="op" name="operational_status">@foreach (\App\Models\Asset::OPERATIONAL as $k => $v)<option value="{{ $k }}" @selected(old('operational_status', 'serviceable') === $k)>{{ $v }}</option>@endforeach</select>
    <label>Foto</label>
    <label class="btn alt" style="margin:0;font-weight:600">📷 Ambil foto<input type="file" name="photos[]" accept="image/*" capture="environment" multiple hidden></label>
    <label for="note">Catatan</label><textarea id="note" name="note">{{ old('note') }}</textarea>
    <button class="btn" style="margin-top:16px">Simpan aset temuan</button>
</form>
@endsection
