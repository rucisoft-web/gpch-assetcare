<x-filament-panels::page>
<style>
    .pp-wrap { display:grid; grid-template-columns:minmax(14rem,18rem) 1fr; gap:1.25rem; align-items:start; }
    @media (max-width: 900px) { .pp-wrap { grid-template-columns:1fr; } }
    .pp-side { position:sticky; top:4.5rem; display:flex; flex-direction:column; gap:.3rem; }
    @media (max-width: 900px) { .pp-side { position:static; flex-direction:row; flex-wrap:wrap; } }
    .pp-side h4 { font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; color:var(--k-emas, #B8862B); margin:.6rem 0 .2rem; font-weight:700; width:100%; }
    .pp-role { display:flex; align-items:center; justify-content:space-between; gap:.5rem; text-align:left; padding:.5rem .7rem; border-radius:.6rem; border:1px solid transparent; background:transparent; cursor:pointer; font-size:.9rem; color:inherit; }
    .pp-role:hover { background:rgba(0,0,0,.04); }
    .pp-role.on { background:var(--k-hijau-gp, #1F5A3A); color:#fff; }
    .pp-dev { font-size:.68rem; padding:.1rem .45rem; border-radius:999px; background:rgba(0,0,0,.06); white-space:nowrap; }
    .pp-role.on .pp-dev { background:rgba(255,255,255,.2); }
    .pp-card { background:#fff; border:1px solid var(--k-garis, #E6DFD0); border-radius:1rem; padding:1.2rem 1.4rem; }
    .dark .pp-card { background:#1d1714; border-color:#3a302a; }
    .pp-head h2 { font-size:1.5rem; font-weight:700; margin:0; }
    .pp-meta { display:flex; flex-wrap:wrap; gap:.5rem; margin:.5rem 0 .2rem; }
    .pp-chip { display:inline-flex; align-items:center; gap:.35rem; font-size:.8rem; font-weight:600; padding:.25rem .65rem; border-radius:999px; background:#E3F1E4; color:#1F5A2B; }
    .pp-chip.alt { background:#F4ECDC; color:#8A6416; }
    .pp-task { display:grid; grid-template-columns:1fr minmax(0, 1.25fr); gap:1.2rem; padding:1.2rem 0; border-top:1px solid var(--k-garis, #E6DFD0); align-items:start; }
    .pp-task.mob { grid-template-columns:1fr 260px; }
    @media (max-width: 1100px) { .pp-task, .pp-task.mob { grid-template-columns:1fr; } }
    .pp-task h3 { font-size:1.05rem; font-weight:700; margin:0 0 .5rem; display:flex; gap:.6rem; align-items:center; }
    .pp-no { width:1.7rem; height:1.7rem; border-radius:50%; background:var(--k-hijau-gp, #1F5A3A); color:#fff; display:grid; place-items:center; font-size:.85rem; flex:none; }
    .pp-task ol { margin:0; padding-left:1.2rem; list-style:decimal; display:flex; flex-direction:column; gap:.35rem; font-size:.92rem; line-height:1.45; }
    .pp-shot { display:block; border:1px solid var(--k-garis, #E6DFD0); border-radius:.7rem; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,.08); background:#f5f1e8; }
    .pp-shot img { display:block; width:100%; height:auto; }
    .pp-task.mob .pp-shot { border-radius:1.4rem; border:6px solid #222; max-width:260px; margin:0 auto; max-height:560px; overflow:hidden; }
    .pp-cap { font-size:.75rem; color:var(--k-abu, #6F665B); text-align:center; margin-top:.35rem; }
</style>
<div class="pp-wrap">
    <nav class="pp-side">
        @if ($mine)
            <h4>Peran Anda</h4>
            @foreach ($mine as $s)
                <button type="button" wire:click="pilih('{{ $s }}')" class="pp-role {{ $peran === $s ? 'on' : '' }}">{{ $all[$s]['nama'] }} <span class="pp-dev">{{ $all[$s]['perangkat'] === 'ponsel' ? 'Ponsel' : ($all[$s]['perangkat'] === 'web' ? 'Web' : 'Web/Ponsel') }}</span></button>
            @endforeach
        @endif
        <h4>{{ $mine ? 'Peran lain' : 'Semua peran' }}</h4>
        @foreach ($all as $s => $r)
            @continue(in_array($s, $mine, true))
            <button type="button" wire:click="pilih('{{ $s }}')" class="pp-role {{ $peran === $s ? 'on' : '' }}">{{ $r['nama'] }} <span class="pp-dev">{{ $r['perangkat'] === 'ponsel' ? 'Ponsel' : ($r['perangkat'] === 'web' ? 'Web' : 'Web/Ponsel') }}</span></button>
        @endforeach
    </nav>

    @if ($g)
    <section class="pp-card" wire:key="peran-{{ $g['slug'] }}">
        <div class="pp-head">
            <h2>{{ $g['nama'] }}</h2>
            <div class="pp-meta">
                <span class="pp-chip">{{ $g['perangkat'] === 'ponsel' ? '📱' : '🖥' }} {{ \App\Support\Help\RoleGuide::PERANGKAT[$g['perangkat']] }}</span>
                <span class="pp-chip alt">Alamat: {{ rtrim(config('app.url'), '/') }}{{ $g['alamat'] }}</span>
                @if (in_array($g['slug'], $mine, true))<span class="pp-chip">Peran Anda</span>@endif
            </div>
            <p class="arah-muted" style="margin:.4rem 0 0">{{ $g['ringkas'] }}</p>
        </div>
        @foreach ($g['tugas'] as $i => [$judul, $langkah, $img, $dev])
            <div class="pp-task {{ $dev === 'ponsel' ? 'mob' : '' }}">
                <div>
                    <h3><span class="pp-no">{{ $i + 1 }}</span>{{ $judul }}</h3>
                    <ol>@foreach ($langkah as $l)<li>{{ $l }}</li>@endforeach</ol>
                </div>
                <div>
                    <a class="pp-shot" href="{{ \App\Support\Help\RoleGuide::image($img) }}" target="_blank" title="Perbesar"><img src="{{ \App\Support\Help\RoleGuide::image($img) }}" alt="{{ $judul }}" loading="lazy"></a>
                    <div class="pp-cap">{{ $dev === 'ponsel' ? 'Tampilan ponsel' : 'Tampilan web' }} · klik untuk memperbesar</div>
                </div>
            </div>
        @endforeach
    </section>
    @endif
</div>
</x-filament-panels::page>
