@extends('field.layout')
@section('title', $asset->asset_code)
@section('heading', $asset->name)
@section('sub', $asset->asset_code)
@section('back', $asset->location_id ? (request()->boolean('pasang') ? route('field.install', $asset->location_id) : route('field.location', $asset->location_id)) : route('field.home'))
@section('content')
<div class="card">
    @if ($asset->mainPhotoUrl())<img class="photo" src="{{ $asset->mainPhotoUrl() }}" alt="Foto aset">@endif
    <dl class="kv">
        <dt>Lokasi</dt><dd>{{ $asset->location?->full_path ?? '—' }}</dd>
        <dt>Kategori</dt><dd>{{ $asset->category?->name }}</dd>
        <dt>Status</dt><dd>@include('field._status', ['a' => $asset]) @if ($asset->verified_at)<span class="muted">{{ $asset->verifier?->name }}, {{ \App\Support\Fmt::dt($asset->verified_at) }}</span>@endif</dd>
        <dt>Label QR</dt><dd>{{ \App\Models\InstallationEvent::STATUSES[$asset->installation_status ?? 'pending'] ?? $asset->installation_status }}</dd>
        @if ($asset->legacy_code)<dt>Kode lama</dt><dd>{{ $asset->legacy_code }}</dd>@endif
    </dl>
</div>
<form method="post" action="{{ route('field.identify', $asset) }}" enctype="multipart/form-data" class="card" id="f">
    @csrf
    @if (request()->boolean('pasang') || old('pasang'))<input type="hidden" name="pasang" value="1">@endif
    <b>Hasil pengecekan</b>
    <label>Aset ada di lokasi ini?</label>
    <div class="seg two">
        <input type="radio" name="found" id="f1" value="1" @checked(old('found', '1') === '1')><label for="f1">Ada</label>
        <input type="radio" name="found" id="f0" value="0" @checked(old('found') === '0')><label for="f0">Tidak ditemukan</label>
    </div>
    @error('found')<div class="err-t">{{ $message }}</div>@enderror

    <div id="ada">
        <label>Kondisi</label>
        <div class="seg">
            @foreach (\App\Models\Asset::CONDITIONS as $k => $v)
                <input type="radio" name="condition_grade" id="c{{ $k }}" value="{{ $k }}" @checked(old('condition_grade', $asset->condition_grade) === $k)><label for="c{{ $k }}">{{ $k }}</label>
            @endforeach
        </div>
        <div class="muted" style="margin-top:6px;font-size:13px">A baik · B rusak ringan · C perlu perbaikan · D kaji penggantian · E tidak layak</div>
        @error('condition_grade')<div class="err-t">{{ $message }}</div>@enderror

        <label for="op">Status operasional</label>
        <select id="op" name="operational_status">
            @foreach (\App\Models\Asset::OPERATIONAL as $k => $v)<option value="{{ $k }}" @selected(old('operational_status', $asset->operational_status) === $k)>{{ $v }}</option>@endforeach
        </select>

        <label for="brand">Merek</label><input id="brand" type="text" name="brand" value="{{ old('brand', $asset->brand) }}">
        <div class="grid2">
            <div><label for="model">Model / tipe</label><input id="model" type="text" name="model" value="{{ old('model', $asset->model) }}"></div>
            <div><label for="sn">No. seri</label><input id="sn" type="text" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}"></div>
        </div>

        <label>Foto aset (boleh lebih dari satu)</label>
        <label class="btn alt" style="margin:0;font-weight:600">📷 Ambil foto<input type="file" name="photos[]" accept="image/*" capture="environment" multiple hidden id="ph"></label>
        <div id="phn" class="muted" style="margin-top:6px"></div>
        @error('photos.*')<div class="err-t">{{ $message }}</div>@enderror
    </div>

    <label for="note">Catatan <span class="muted" id="wajib" style="font-weight:400"></span></label>
    <textarea id="note" name="note" placeholder="mis. remote hilang, label lama terkelupas, dipindah ke kamar 805">{{ old('note') }}</textarea>
    @error('note')<div class="err-t">{{ $message }}</div>@enderror

    <button class="btn" style="margin-top:16px">Simpan{{ $next ? ' & lanjut' : '' }}</button>
</form>
@if ($next)<a class="row card" href="{{ route('field.asset', $next) }}"><span class="t"><span class="muted">Berikutnya di lokasi ini</span><b>{{ $next->name }}</b></span><span class="chev">›</span></a>@endif
@endsection
@push('scripts')
<script>
(function () {
  const ada = document.getElementById('ada'), w = document.getElementById('wajib');
  const sync = () => { const no = document.getElementById('f0').checked; ada.style.display = no ? 'none' : ''; w.textContent = no ? '(wajib: jelaskan)' : '(opsional)'; };
  document.querySelectorAll('input[name=found]').forEach(r => r.addEventListener('change', sync)); sync();
  document.getElementById('ph').addEventListener('change', e => { const n = e.target.files.length; document.getElementById('phn').textContent = n ? n + ' foto dipilih' : ''; });
  document.getElementById('f').addEventListener('submit', e => { const b = e.target.querySelector('button'); b.disabled = true; b.textContent = 'Menyimpan…'; });
})();
</script>
@endpush
