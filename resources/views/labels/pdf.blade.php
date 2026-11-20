@php
    // compact: label pendek (pita 18/24 mm, 50 × 25 mm): tanpa baris merek & catatan kaki agar teks tidak bertumpuk
    $compact = $p['label_h'] < 28;
    $pad = $p['label_h'] >= 40 ? 2.5 : ($compact ? 1.4 : 1.8);
    $qr = min($p['qr_mm'], $p['label_h'] - 2 * $pad);
    $textW = $p['label_w'] - $qr - 3 * $pad;
    $big = $p['label_h'] >= 40;
    $wide = ! $big && ! $compact && $p['label_w'] >= 60; // mis. Tom & Jerry 64 × 32 mm: teks lebih besar
    $brand = \App\Support\Brand::labelHeading();
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $batch->number }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1c1917; }
    .sheet { position: relative; width: {{ $p['page_w'] }}mm; height: {{ $p['page_h'] }}mm; page-break-after: always; overflow: hidden; }
    .sheet:last-child { page-break-after: auto; }
    .cell { position: absolute; width: {{ $p['label_w'] }}mm; height: {{ $p['label_h'] }}mm; overflow: hidden; }
    .guide { border: 0.2mm dashed #a8a29e; border-radius: {{ $p['radius'] ?? 0 }}mm; }
    .qr { position: absolute; left: {{ $pad }}mm; top: {{ ($p['label_h'] - $qr) / 2 }}mm; width: {{ $qr }}mm; height: {{ $qr }}mm; }
    .txt { position: absolute; left: {{ $qr + 2 * $pad }}mm; top: {{ $pad }}mm; width: {{ $textW }}mm; height: {{ $p['label_h'] - 2 * $pad }}mm; overflow: hidden; }
    .brand { font-size: {{ $big ? 6.5 : ($wide ? 5.8 : 5) }}pt; color: {{ \App\Support\Brand::color() }}; font-weight: bold; letter-spacing: .2pt; text-transform: uppercase; }
    .code { font-size: {{ $big ? 9.5 : ($wide ? 8.4 : 7) }}pt; font-weight: bold; margin-top: .6mm; }
    .name { font-size: {{ $big ? 7.5 : ($wide ? 6.8 : 5.6) }}pt; margin-top: .6mm; line-height: 1.15; max-height: {{ $big ? 9 : ($wide ? 7 : ($compact && $p['label_h'] < 20 ? 2.6 : 5.2)) }}mm; overflow: hidden; }
    .loc { font-size: {{ $big ? 7 : ($wide ? 6.6 : 5.4) }}pt; margin-top: .6mm; font-weight: bold; color: #44403c; }
    .foot { position: absolute; bottom: 0; left: 0; font-size: {{ $big ? 5.5 : ($wide ? 5 : 4.2) }}pt; color: #57534e; }
    .copy { position: absolute; right: .8mm; bottom: .4mm; font-size: 3.6pt; color: #a8a29e; }
</style>
</head>
<body>
@php
    $blockW = $p['cols'] * $p['label_w'] + ($p['cols'] - 1) * $p['gap_x'];
    $blockH = $p['rows'] * $p['label_h'] + ($p['rows'] - 1) * $p['gap_y'];
@endphp
@foreach ($sheets as $sheetNo => $cells)
    <div class="sheet">
        @if ($guides && ($p['carrier'] ?? false))
            <div style="position:absolute; left:{{ $p['margin_left'] - 3 }}mm; top:{{ $p['margin_top'] - 3 }}mm; width:{{ $blockW + 6 }}mm; height:{{ $blockH + 6 }}mm; border:0.3mm dashed #b45309; box-sizing:border-box;"></div>
            <div style="position:absolute; left:{{ $p['margin_left'] - 3 }}mm; top:{{ $p['margin_top'] - 7 }}mm; font-size:6.5pt; color:#b45309;">Posisi lembar label (tempel lembar Tom &amp; Jerry tepat di sini)</div>
        @endif
        @if (($test ?? false) && $p['type'] === 'a4')
            <div style="position:absolute; left:15mm; right:15mm; top:{{ min($p['page_h'] - 60, $p['margin_top'] + $blockH + 10) }}mm; font-size:7.5pt; color:#44403c; line-height:1.45;">
                <b>LEMBAR UJI · {{ $p['name'] }}</b><br>
                1. Cetak halaman ini di kertas HVS biasa dengan skala 100% (matikan "Fit to page").<br>
                2. Letakkan lembar label di atas hasil cetak, terawang ke arah lampu: setiap kotak garis harus tepat di tengah label.<br>
                3. Bila meleset, ukur selisihnya (mm) lalu ubah Kalibrasi di halaman Cetak label: geser kiri/atas, jarak antar-label. Cetak uji lagi.<br>
                4. Bila sudah pas, tempel lembar label pada kertas A4 tepat di kotak putus-putus jingga (selotip di tepi atas), lalu cetak label asli.<br>
                Kalibrasi saat ini: kiri {{ $p['margin_left'] }} mm · atas {{ $p['margin_top'] }} mm · jarak kolom {{ $p['gap_x'] }} mm · jarak baris {{ $p['gap_y'] }} mm · {{ $p['cols'] }} × {{ $p['rows'] }}.
            </div>
        @endif
        @foreach ($cells as $c)
            @php
                $i = $c['item'];
                $idx = $i->cell_no - 1;
                $col = $idx % $p['cols'];
                $row = intdiv($idx, $p['cols']);
                $left = $p['margin_left'] + $col * ($p['label_w'] + $p['gap_x']);
                $top = $p['margin_top'] + $row * ($p['label_h'] + $p['gap_y']);
            @endphp
            <div class="cell {{ $guides ? 'guide' : '' }}" style="left: {{ $left }}mm; top: {{ $top }}mm;">
                <img class="qr" src="{{ $c['qr'] }}" alt="">
                <div class="txt">
                    @unless ($compact)<div class="brand">{{ $brand }}</div>@endunless
                    @php
                        // kode panjang dipecah di batas lokasi | jenis-urut: "WW-03-305" / "AC-SPLIT-01"
                        $code = (string) $i->code_snapshot;
                        $locPart = ($i instanceof \App\Models\LabelPrintItem && $i->location) ? \App\Support\AssetCode::locationPart($i->location) : (preg_match('/^([A-Z]+-\d+-\d+)-/', $code, $mm) ? $mm[1] : null);
                        $lines = ($locPart && str_starts_with($code, $locPart.'-') && mb_strlen($code) * 0.78 * 0.3528 * ($big ? 9.5 : ($wide ? 8.4 : 7)) > $textW) ? [$locPart, substr($code, strlen($locPart) + 1)] : [$code];
                        $longest = max(array_map('mb_strlen', $lines));
                        $maxPt = $big ? 9.5 : ($wide ? 8.4 : 7);
                        $fitPt = round(min($maxPt, max($big ? 6 : 4.6, ($textW / ($longest * 0.78 * 0.3528)))), 1);
                    @endphp
                    <div class="code" style="font-size:{{ $fitPt }}pt; line-height:1.12; word-break:break-all">{!! collect($lines)->map(fn ($l) => e($l))->join('<br>') !!}</div>
                    <div class="name">{{ \Illuminate\Support\Str::limit($i->name_snapshot, $big ? 60 : ($wide ? 50 : 38)) }}</div>
                    @if ($i->location_snapshot)<div class="loc">{{ \Illuminate\Support\Str::limit($i->location_snapshot, $wide ? 34 : 28) }}</div>@endif
                    @unless ($compact)<div class="foot">Scan untuk info &amp; lapor kerusakan</div>@endunless
                </div>
                <div class="copy">{{ $batch->number }}·{{ $i->sequence }}</div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
