@php use App\Models\Asset; use App\Support\Fmt; @endphp
<x-filament-panels::page>
<style>
    .pa-wrap { display: grid; grid-template-columns: minmax(16rem, 22rem) 1fr; gap: 1rem; align-items: start; }
    @media (max-width: 900px) { .pa-wrap { grid-template-columns: 1fr; } }
    .pa-side { position: sticky; top: 4.5rem; max-height: calc(100vh - 6rem); overflow: auto; }
    .pa-tree { list-style: none; margin: 0; padding-left: .9rem; border-left: 1px dashed var(--k-garis); }
    .pa-tree.root { padding-left: 0; border-left: 0; }
    .pa-tree details > summary { list-style: none; cursor: pointer; }
    .pa-tree details > summary::-webkit-details-marker { display: none; }
    .pa-tree details > summary::before { content: '▸'; display: inline-block; width: .9rem; color: var(--k-tinta-2); transition: transform .15s; }
    .pa-tree details[open] > summary::before { transform: rotate(90deg); }
    .pa-row { display: flex; align-items: center; gap: .35rem; padding: .18rem .3rem; border-radius: .45rem; font-size: .84rem; }
    .pa-leaf { padding-left: 1.2rem; }
    .pa-row:hover { background: var(--k-kertas-2); }
    .pa-row.is-sel { background: rgba(0,102,50,.12); }
    .pa-row.is-sel .pa-name { color: var(--k-hijau-gp); font-weight: 700; }
    .pa-name { flex: 1; text-align: left; background: none; border: 0; padding: 0; color: var(--k-tinta); cursor: pointer; font: inherit; }
    .pa-count { font-variant-numeric: tabular-nums; font-size: .72rem; font-weight: 700; padding: 0 .45rem; border-radius: 999px; background: rgba(0,102,50,.10); color: var(--k-hijau-gp); }
    .pa-count.nol { background: transparent; color: var(--k-tinta-2); font-weight: 400; }
    .pa-chips { display: flex; flex-wrap: wrap; gap: .35rem; }
    .pa-chip { display: inline-flex; gap: .35rem; align-items: center; border: 1px solid var(--k-garis); background: var(--k-kartu); border-radius: 999px; padding: .2rem .6rem; font-size: .76rem; cursor: pointer; }
    .pa-chip.on { background: var(--k-hijau-gp); color: #fff; border-color: var(--k-hijau-gp); }
    .pa-chip b { font-variant-numeric: tabular-nums; }
    .pa-crumb { display: flex; flex-wrap: wrap; gap: .3rem; align-items: center; font-size: .8rem; }
    .pa-crumb button { background: none; border: 0; padding: 0; color: var(--k-link); font-weight: 600; cursor: pointer; font: inherit; }
    .pa-class { margin-bottom: .5rem; }
    .pa-class > .pa-row { font-weight: 700; }
</style>
<div class="arah">
    <div class="arah-card" style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:center; justify-content:space-between">
        <div style="display:flex; gap:.4rem">
            <button type="button" wire:click="setMode('lokasi')" class="arah-btn {{ $mode === 'lokasi' ? 'arah-btn-primary' : '' }}">Per lokasi</button>
            <button type="button" wire:click="setMode('jenis')" class="arah-btn {{ $mode === 'jenis' ? 'arah-btn-primary' : '' }}">Per jenis aset</button>
        </div>
        <div style="display:flex; gap:.8rem; align-items:center; flex-wrap:wrap">
            <input type="search" class="ac-input" placeholder="Cari kode / nama / no. seri" wire:model.live.debounce.400ms="search" style="min-width:14rem">
            <label class="arah-muted" style="display:flex; gap:.3rem; align-items:center"><input type="checkbox" wire:model.live="includeInactive"> termasuk berhenti dipakai</label>
            @if ($mode === 'lokasi')
                <label class="arah-muted" style="display:flex; gap:.3rem; align-items:center"><input type="checkbox" wire:model.live="hideEmpty"> sembunyikan lokasi kosong</label>
            @endif
        </div>
    </div>

    <div class="pa-wrap" style="margin-top:1rem">
        {{-- panel kiri --}}
        <div class="arah-card pa-side">
            @if ($mode === 'lokasi')
                <h3>Pohon lokasi <small>{{ $grandTotal }} aset</small></h3>
                @if ($typeLabel)
                    <p class="arah-muted" style="margin:.2rem 0 .5rem">Hanya jenis <b>{{ $typeLabel }}</b> · <button type="button" wire:click="clearType" style="background:none;border:0;color:var(--k-link);cursor:pointer;padding:0">hapus filter</button></p>
                @endif
                <div class="pa-row {{ $selected ? '' : 'is-sel' }}" style="padding-left:.3rem">
                    <button type="button" wire:click="pickLocation(null)" class="pa-name">Semua lokasi</button>
                    <span class="pa-count">{{ $grandTotal }}</span>
                </div>
                <ul class="pa-tree root">
                    @foreach ($tree['children']->get(0, collect()) as $root)
                        @include('filament.pages.partials.peta-node', ['node' => $root, 'depth' => 0, 'selectedId' => $selected?->id, 'hideEmpty' => $hideEmpty])
                    @endforeach
                </ul>
                @if ($unplaced)
                    <p class="arah-muted" style="margin-top:.6rem">{{ $unplaced }} aset belum punya lokasi.</p>
                @endif
            @else
                <h3>Jenis aset <small>kelas kebijakan › kategori</small></h3>
                <div class="pa-row {{ $classId || $categoryId ? '' : 'is-sel' }}">
                    <button type="button" wire:click="pickClass(null)" class="pa-name">Semua jenis</button>
                </div>
                @forelse ($classes as $k)
                    <div class="pa-class">
                        <div class="pa-row {{ $classId === $k['id'] && ! $categoryId && $k['id'] ? 'is-sel' : '' }}">
                            @if ($k['id'])
                                <button type="button" wire:click="pickClass({{ $k['id'] }})" class="pa-name">{{ $k['code'] }} · {{ $k['name'] }}</button>
                            @else
                                <span class="pa-name">{{ $k['name'] }}</span>
                            @endif
                            <span class="pa-count">{{ $k['n'] }}</span>
                        </div>
                        <ul class="pa-tree">
                            @foreach ($k['cats'] as $c)
                                <li><div class="pa-row pa-leaf {{ $categoryId === $c['id'] ? 'is-sel' : '' }}">
                                    <button type="button" wire:click="pickCategory({{ $c['id'] }})" class="pa-name">{{ $c['name'] }}</button>
                                    <span class="pa-count">{{ $c['n'] }}</span>
                                </div></li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <div class="arah-empty">Belum ada aset.</div>
                @endforelse
            @endif
        </div>

        {{-- panel kanan --}}
        <div style="display:flex; flex-direction:column; gap:1rem; min-width:0">
            <div class="arah-card">
                @if ($mode === 'lokasi')
                    <div class="pa-crumb">
                        <button type="button" wire:click="pickLocation(null)">Semua lokasi</button>
                        @if ($selected)
                            @foreach ($selected->ancestors() as $anc)
                                <span class="arah-muted">›</span><button type="button" wire:click="pickLocation({{ $anc->id }})">{{ $anc->name }}</button>
                            @endforeach
                            <span class="arah-muted">›</span><b>{{ $selected->name }}</b>
                            <span class="arah-badge arah-abu">{{ \App\Models\Location::TYPES[$selected->location_type] ?? $selected->location_type }}</span>
                        @endif
                    </div>
                    <h3 style="margin-top:.5rem">{{ $selected?->name ?? 'Semua lokasi' }} <small>{{ $count }} aset{{ $selected ? ' (termasuk sub-lokasi)' : '' }}{{ $typeLabel ? ' · '.$typeLabel : '' }}</small></h3>
                @else
                    <h3>{{ $typeLabel ?? 'Semua jenis aset' }} <small>{{ $count }} aset</small></h3>
                @endif
                <div class="arah-grid arah-grid-4" style="margin-top:.4rem">
                    <div class="arah-stat"><span class="label">Jumlah aset</span><span class="value">{{ $count }}</span></div>
                    <div class="arah-stat"><span class="label">Jenis (kategori)</span><span class="value">{{ $categories->count() }}</span></div>
                    @if ($mode === 'jenis' && ($classId || $categoryId))
                        <div class="arah-stat"><span class="label">Tersebar di</span><span class="value">{{ $spread->count() }}</span><span class="sub">lokasi</span></div>
                    @endif
                    @if ($fin)
                        <div class="arah-stat"><span class="label">Biaya perolehan</span><span class="value" style="font-size:1.4rem">{{ Fmt::rp($costTotal) }}</span></div>
                    @endif
                </div>
                @if ($categories->isNotEmpty() && ! $categoryId)
                    <p class="arah-muted" style="margin:.8rem 0 .4rem">Jenis aset di sini — klik untuk menyaring:</p>
                    <div class="pa-chips">
                        @foreach ($categories as $c)
                            <button type="button" class="pa-chip" wire:click="pickCategory({{ $c['id'] }})">{{ $c['name'] }} <b>{{ $c['n'] }}</b></button>
                        @endforeach
                    </div>
                @elseif ($categoryId)
                    <div class="pa-chips" style="margin-top:.8rem">
                        <button type="button" class="pa-chip on" wire:click="clearType">{{ $typeLabel }} ✕</button>
                    </div>
                @endif
            </div>

            @if ($mode === 'jenis' && ($classId || $categoryId))
                <div class="arah-card">
                    <h3>Ada di mana saja <small>{{ $spread->count() }} lokasi</small></h3>
                    @if ($spread->isEmpty())
                        <div class="arah-empty">Tidak ada aset jenis ini.</div>
                    @else
                        <div class="arah-scroll" style="max-height:22rem">
                            <table class="arah-table">
                                <thead><tr><th>Lokasi</th><th class="num">Jumlah</th><th></th></tr></thead>
                                <tbody>
                                @foreach ($spread as $r)
                                    <tr>
                                        <td>{{ $r['location']?->full_path ?? '(tanpa lokasi)' }}</td>
                                        <td class="num">{{ $r['n'] }}</td>
                                        <td>@if ($r['location'])<a href="#" wire:click.prevent="showInTree({{ $r['location']->id }})">lihat di pohon</a>@endif</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            <div class="arah-card">
                <h3>Daftar aset <small>{{ $count > 300 ? '300 dari '.$count.' — persempit dengan pencarian atau pilih lokasi' : $count.' aset' }}</small></h3>
                @if ($assets->isEmpty())
                    <div class="arah-empty">Tidak ada aset pada pilihan ini.</div>
                @else
                    <div class="arah-scroll">
                        <table class="arah-table">
                            <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Lokasi</th><th>Kondisi</th><th>Operasional</th>@if ($fin)<th class="num">Biaya perolehan</th>@endif</tr></thead>
                            <tbody>
                            @foreach ($assets as $a)
                                <tr>
                                    <td><a href="{{ \App\Filament\Resources\Assets\AssetResource::getUrl('view', ['record' => $a]) }}" style="font-family:monospace; white-space:nowrap">{{ $a->asset_code }}</a></td>
                                    <td>{{ $a->name }}@if ($a->lifecycle_status !== 'active')<br><span class="arah-badge arah-abu">{{ Asset::LIFECYCLE[$a->lifecycle_status] ?? $a->lifecycle_status }}</span>@endif</td>
                                    <td>{{ $a->category?->name }}</td>
                                    <td>@if ($a->location)<a href="#" wire:click.prevent="showInTree({{ $a->location_id }})">{{ $a->location->full_path }}</a>@else — @endif</td>
                                    <td>{{ $a->condition_grade ?? '—' }}</td>
                                    <td><span class="arah-badge {{ match ($a->operational_status) { 'serviceable' => 'arah-hijau', 'degraded' => 'arah-kuning', default => 'arah-merah' } }}">{{ Asset::OPERATIONAL[$a->operational_status] ?? $a->operational_status }}</span></td>
                                    @if ($fin)<td class="num">{{ $a->financial?->acquisition_cost !== null ? Fmt::rp($a->financial->acquisition_cost) : '—' }}</td>@endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
</x-filament-panels::page>
