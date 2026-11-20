@php
    $labels = $this->labels;
    $lay = $this->layout;
    $reprintCount = $labels->where('print_items_count', '>', 0)->count();
    $profiles = config('assetcare.label_profiles');
@endphp
<x-filament-panels::page>
<div class="arah">
    <div class="arah-grid arah-grid-2">
        <div class="arah-card">
            <h3 style="margin:0 0 .6rem">Pilih aset</h3>
            @if ($assetIds !== '')
                <div class="ac-scan-msg">Mencetak aset terpilih ({{ count(array_filter(explode(',', $assetIds))) }}). <a href="#" wire:click.prevent="resetFilters">Pakai filter lokasi</a></div>
            @else
                <label class="arah-muted">Lantai / gedung</label>
                <select class="ac-input" wire:model.live="floorId">
                    <option value="">— semua —</option>
                    @foreach ($this->floors as $f)<option value="{{ $f->id }}">{{ $f->full_path }}</option>@endforeach
                </select>
                <div class="ac-pick-head">
                    <label class="arah-muted">Kamar / ruang tertentu (boleh banyak)
                        <span class="ac-pick-note">{{ $this->rooms->count() }} ruang{{ ! $allRooms && $floorId ? ' di lantai/gedung terpilih' : '' }}{{ count($roomIds) ? ' · '.count($roomIds).' dipilih' : '' }}</span></label>
                    <span class="ac-pick-actions">
                        @if (count($roomIds))<button type="button" class="ac-pick-btn" wire:click="$set('roomIds', [])">Kosongkan</button>@endif
                        @if ($floorId)<button type="button" class="ac-pick-btn" wire:click="$toggle('allRooms')">{{ $allRooms ? 'Sesuai lantai' : 'Tampilkan semua' }}</button>@endif
                    </span>
                </div>
                <select class="ac-input" multiple size="5" wire:model.live="roomIds">
                    @foreach ($this->rooms as $r)<option value="{{ $r->id }}">{{ $r->labelText() }} — {{ $r->full_path }}</option>@endforeach
                </select>
                <div class="ac-pick-head">
                    <label class="arah-muted">Kategori
                        <span class="ac-pick-note">{{ $this->categories->count() }} kategori{{ ! $allCategories && ($floorId || count($roomIds)) ? ' yang ada di '.(count($roomIds) ? 'kamar' : 'lantai/gedung').' terpilih' : '' }}{{ count($categoryIds) ? ' · '.count($categoryIds).' dipilih' : '' }}</span></label>
                    <span class="ac-pick-actions">
                        @if (count($categoryIds))<button type="button" class="ac-pick-btn" wire:click="$set('categoryIds', [])">Kosongkan</button>@endif
                        @if ($floorId || count($roomIds))<button type="button" class="ac-pick-btn" wire:click="$toggle('allCategories')">{{ $allCategories ? 'Sesuai lokasi' : 'Tampilkan semua' }}</button>@endif
                    </span>
                </div>
                <select class="ac-input" multiple size="4" wire:model.live="categoryIds">
                    @foreach ($this->categories as $c)<option value="{{ $c->id }}">{{ $c->name }}@isset($c->scope_count) ({{ $c->scope_count }})@endisset</option>@endforeach
                </select>
                <label class="arah-muted" style="display:block; margin-top:.5rem">Batch generate</label>
                <select class="ac-input" wire:model.live="generationBatchId">
                    <option value="">— semua —</option>
                    @foreach ($this->batches as $b)<option value="{{ $b->id }}">{{ $b->number }} · {{ $b->created_assets }} aset</option>@endforeach
                </select>
            @endif
            <label class="arah-muted" style="display:block; margin-top:.5rem">Status cetak</label>
            <select class="ac-input" wire:model.live="printState">
                <option value="all">Semua label aktif</option>
                <option value="never_printed">Belum pernah dicetak</option>
                <option value="not_installed">Belum terpasang</option>
            </select>
            <label style="display:flex; gap:.4rem; margin-top:.6rem; font-size:.85rem"><input type="checkbox" wire:model.live="includeUnverified"> Sertakan aset yang belum dicek di lapangan (biarkan dicentang untuk alur cetak → tempel → scan)</label>
            <label style="display:flex; gap:.4rem; font-size:.85rem"><input type="checkbox" wire:model.live="includeGroups"> Sertakan kelompok jumlah</label>
        </div>
        <div class="arah-card">
            <h3 style="margin:0 0 .6rem">Media & tata letak</h3>
            <select class="ac-input" wire:model.live="profile">
                @foreach ($profiles as $k => $p)<option value="{{ $k }}">{{ $p['name'] }}</option>@endforeach
            </select>
            <div class="arah-grid arah-grid-2" style="margin-top:.5rem">
                <div><label class="arah-muted">Mulai dari sel (lembar sisa)</label>
                    <input type="number" min="1" max="{{ $lay['per_sheet'] }}" class="ac-input" wire:model.live.debounce.400ms="startCell" @disabled($lay['profile']['type'] !== 'a4')></div>
                <div><label class="arah-muted">Salinan per label</label>
                    <input type="number" min="1" max="5" class="ac-input" wire:model.live.debounce.400ms="copies"></div>
            </div>
            @if ($cal)
            <details style="margin-top:.7rem" @if (($profiles[$profile]['carrier'] ?? false)) open @endif>
                <summary style="cursor:pointer; font-weight:600">Kalibrasi posisi label (mm)</summary>
                <p class="arah-muted" style="margin:.4rem 0">Jarak dari tepi kiri/atas kertas A4 ke label pertama, dan jarak antar-label. Ukur lembar label dengan penggaris, isi, simpan, lalu cetak lembar uji di kertas biasa dan tumpuk dengan lembar label.</p>
                <div class="arah-grid arah-grid-3">
                    <div><label class="arah-muted">Dari kiri</label><input type="number" step="0.5" class="ac-input" wire:model="cal.margin_left"></div>
                    <div><label class="arah-muted">Dari atas</label><input type="number" step="0.5" class="ac-input" wire:model="cal.margin_top"></div>
                    <div><label class="arah-muted">Kolom × baris</label><div style="display:flex; gap:.3rem"><input type="number" min="1" class="ac-input" wire:model="cal.cols"><input type="number" min="1" class="ac-input" wire:model="cal.rows"></div></div>
                    <div><label class="arah-muted">Jarak antar-kolom</label><input type="number" step="0.5" class="ac-input" wire:model="cal.gap_x"></div>
                    <div><label class="arah-muted">Jarak antar-baris</label><input type="number" step="0.5" class="ac-input" wire:model="cal.gap_y"></div>
                </div>
                <div class="ac-actions" style="margin-top:.5rem">
                    <button type="button" class="arah-btn arah-btn-primary" wire:click="saveCal">Simpan kalibrasi</button>
                    <a class="arah-btn" href="{{ route('labels.test', $profile) }}" target="_blank">Cetak lembar uji</a>
                    <button type="button" class="arah-btn" wire:click="resetCal">Kembalikan bawaan</button>
                </div>
            </details>
            @endif
            <div class="arah-kamasan" style="margin:.8rem 0"></div>
            <div class="arah-grid arah-grid-3">
                <div class="arah-stat"><span class="label">Label</span><span class="value">{{ $lay['labels'] }}</span><span class="sub">{{ $labels->count() }} aset/kelompok</span></div>
                <div class="arah-stat"><span class="label">Lembar</span><span class="value">{{ $lay['sheets'] }}</span><span class="sub">{{ $lay['per_sheet'] }} per lembar</span></div>
                <div class="arah-stat"><span class="label">Cetak ulang</span><span class="value" style="{{ $reprintCount ? 'color:var(--k-merah)' : '' }}">{{ $reprintCount }}</span><span class="sub">pernah dicetak</span></div>
            </div>
            @if ($reprintCount)
                <label class="arah-muted" style="display:block; margin-top:.6rem">Alasan cetak ulang (wajib)</label>
                <input type="text" class="ac-input" wire:model="reason" placeholder="mis. label rusak / pudar / salah tempel">
            @endif
            <p class="arah-muted" style="margin-top:.6rem">Cetak pada <b>skala 100%</b> (matikan "fit to page"). Uji satu lembar dulu dan scan dengan 2 jenis ponsel sebelum cetak massal.</p>
            <div class="ac-actions">
                <button type="button" class="arah-btn arah-btn-primary" wire:click="generate" wire:loading.attr="disabled" @disabled(! $labels->count())>Buat PDF label</button>
            </div>
        </div>
    </div>

    <div class="arah-card" style="margin-top:1rem">
        <h3 style="margin:0 0 .6rem">Urutan cetak ({{ $labels->count() }})</h3>
        @if ($labels->isEmpty())
            <div class="arah-empty">Tidak ada label aktif yang cocok. Aset yang belum terverifikasi hanya muncul bila opsi kandidat dicentang.</div>
        @else
            <div class="arah-scroll" style="max-height:26rem">
                <table class="arah-table">
                    <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Lokasi pada label</th><th>Dicetak</th><th>Pemasangan</th></tr></thead>
                    <tbody>
                    @foreach ($labels->take(300) as $n => $l)
                        @php $loc = app(\App\Services\LabelService::class)->targetLocation($l); @endphp
                        <tr>
                            <td>{{ $n + 1 }}</td>
                            <td><b>{{ $l->asset?->asset_code ?? $l->quantityGroup?->group_code }}</b></td>
                            <td>{{ $l->asset?->name ?? $l->quantityGroup?->name }} @if($l->asset && $l->asset->verification_status !== 'verified')<span class="arah-badge arah-kuning">kandidat</span>@endif</td>
                            <td>{{ $loc?->labelText() ?? '—' }}</td>
                            <td>{{ $l->print_items_count ? $l->print_items_count.'×' : '—' }}</td>
                            <td>{{ \App\Models\InstallationEvent::STATUSES[$l->currentInstallationStatus()] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if ($labels->count() > 300)<p class="arah-muted">… dan {{ $labels->count() - 300 }} lainnya.</p>@endif
            </div>
        @endif
    </div>
</div>
</x-filament-panels::page>
