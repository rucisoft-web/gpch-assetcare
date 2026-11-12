@php
    $w = $dims['width'];
    $h = $dims['height'];
    $roll = $w !== null;
    // Skala huruf mengikuti tinggi label (acuan 30 mm).
    $k = $roll ? $h / 30 : 1;
    $qr = $roll ? $h - 3 : 42;
    $tiny = $roll && $h < 22;
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Label QR Aset</title>
<style>
*{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;margin:0;color:#0b2f4f}
.bar{padding:10px 12px;background:#0b3a5c;color:#fff;display:flex;gap:10px;flex-wrap:wrap;justify-content:space-between;align-items:center;font-size:14px}
.bar form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.bar select{padding:6px 8px;border-radius:6px;border:0;font-size:14px;max-width:100%}
.bar button{background:#c9963e;color:#fff;border:0;padding:8px 16px;border-radius:6px;font-weight:700;cursor:pointer;font-size:14px}
.hint{font-size:12px;opacity:.85;width:100%}
@if (! $roll)
@page{size:A4;margin:10mm}
.sheet{display:grid;grid-template-columns:repeat(2,1fr);gap:8mm;padding:8mm}
.label{border:2px solid #0b2f4f;border-radius:12px;padding:5mm;text-align:center;break-inside:avoid;page-break-inside:avoid}
.label .brand{font-size:12px;font-weight:700;letter-spacing:1px}
.label .sub{font-size:8px;margin:2px 0 6px;letter-spacing:.5px}
.label svg{width:42mm;height:42mm}
.label .name{font-size:14px;font-weight:700;margin-top:4px}
.label .meta{font-size:10px;margin-top:2px}
.label .foot{font-size:8px;margin-top:6px;border-top:1px solid #ddd;padding-top:4px;letter-spacing:.5px}
@media print{.bar{display:none}.sheet{padding:0}}
@else
@page{size:{{ $w }}mm {{ $h }}mm;margin:0}
.sheet{padding:12px;display:flex;flex-wrap:wrap;gap:12px;background:#eef2f5}
.label{width:{{ $w }}mm;height:{{ $h }}mm;background:#fff;display:flex;align-items:center;gap:1.2mm;padding:1.2mm 1.5mm;overflow:hidden;outline:1px dashed #9aa}
.label svg{width:{{ $qr }}mm;height:{{ $qr }}mm;flex:none}
.label .txt{min-width:0;display:flex;flex-direction:column;justify-content:center;gap:{{ 0.4 * $k }}mm;line-height:1.1}
.label .brand{font-size:{{ round(5 * $k, 1) }}pt;font-weight:700;letter-spacing:.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.label .code{font-size:{{ round(7 * $k, 1) }}pt;font-weight:700;white-space:nowrap}
.label .name{font-size:{{ round(6.5 * $k, 1) }}pt;font-weight:700;display:-webkit-box;-webkit-line-clamp:{{ $tiny ? 1 : 2 }};-webkit-box-orient:vertical;overflow:hidden}
.label .loc{font-size:{{ round(5.5 * $k, 1) }}pt;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media print{.bar{display:none}.sheet{padding:0;gap:0;display:block;background:none}.label{outline:0;break-after:page;page-break-after:always}}
@endif
</style>
</head>
<body>
<div class="bar">
    <span>{{ $assets->count() }} label QR</span>
    <form method="get">
        @foreach (request()->only(['ids', 'location']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <label for="size">Ukuran</label>
        <select id="size" name="size" onchange="this.form.submit()">
            @foreach (\App\Support\LabelSize::options() as $key => $text)
                <option value="{{ $key }}" @selected($key === $size)>{{ $text }}</option>
            @endforeach
        </select>
        <button type="button" onclick="window.print()">Cetak</button>
    </form>
    <div class="hint">
        @if ($roll)
            Satu label per halaman. Di dialog cetak pilih printer label, ukuran kertas {{ $w }} × {{ $h }} mm, margin "Tidak ada", skala 100%. Dari HP Android: pasang aplikasi/plugin cetak dari merek printer (mis. Brother iPrint&amp;Label, Zebra Printing, atau aplikasi printer Bluetooth) lalu pilih printer itu saat mencetak.
        @else
            Pakai kertas A4 atau stiker A4, skala 100%.
        @endif
    </div>
</div>
<div class="sheet">
@foreach ($assets as $asset)
    @if ($roll)
        <div class="label">
            {!! \App\Support\Qr::svg($asset->qrUrl()) !!}
            <div class="txt">
                @unless ($tiny)<div class="brand">{{ mb_strtoupper($asset->company->code ?: $asset->company->name) }} · ASET</div>@endunless
                <div class="code">{{ $asset->code }}</div>
                <div class="name">{{ $asset->name }}</div>
                @unless ($tiny)<div class="loc">{{ $asset->location?->name }}</div>@endunless
            </div>
        </div>
    @else
        <div class="label">
            <div class="brand">{{ mb_strtoupper($asset->company->name) }}</div>
            <div class="sub">IDENTIFIKASI ASET &amp; PELAPORAN LEWAT HP</div>
            {!! \App\Support\Qr::svg($asset->qrUrl()) !!}
            <div class="name">{{ $asset->name }}</div>
            <div class="meta"><b>ID Aset:</b> {{ $asset->code }}</div>
            <div class="meta"><b>Lokasi:</b> {{ $asset->location?->complete_name }}</div>
            <div class="foot">PINDAI UNTUK MELIHAT ASET / MELAPORKAN MASALAH</div>
        </div>
    @endif
@endforeach
</div>
</body>
</html>
