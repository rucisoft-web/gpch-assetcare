@php
    use App\Models\GenerationBatch;
    $tpl = $this->template;
    $totals = $this->totals;
    $stateLabel = ['mapped' => 'Sudah terpetakan', 'candidate' => 'Ada kandidat aset lama', 'create' => 'Akan dibuat (draft)', 'excess' => 'Melebihi standar', 'not_available' => 'Tidak tersedia'];
@endphp
<x-filament-panels::page>
<div class="arah">
    <ol class="ac-steps">
        <li class="ac-step {{ $tpl ? 'done' : 'on' }}">1. Template</li>
        <li class="ac-step {{ $roomIds ? 'done' : ($tpl ? 'on' : '') }}">2. Kamar</li>
        <li class="ac-step {{ $preview ? 'done' : ($roomIds ? 'on' : '') }}">3. Preview & pencocokan</li>
        <li class="ac-step {{ $batchId ? 'done' : ($preview ? 'on' : '') }}">4. Buat kandidat</li>
        <li class="ac-step {{ $batchId ? 'on' : '' }}">5. Cetak label & verifikasi</li>
    </ol>

    <div class="arah-grid arah-grid-2">
        <div class="arah-card">
            <h3 style="margin:0 0 .6rem">1 · Template published</h3>
            <select class="ac-input" wire:model.live="templateId">
                <option value="">— pilih template —</option>
                @foreach ($this->templates as $t)
                    <option value="{{ $t->id }}">{{ $t->code }} v{{ $t->version }} · {{ $t->name }} ({{ $t->roomType?->name }})</option>
                @endforeach
            </select>
            @if ($tpl)
                <table class="arah-table" style="margin-top:.8rem">
                    <thead><tr><th>Item</th><th>Jml</th><th>Mode</th><th>Posisi</th></tr></thead>
                    <tbody>
                    @foreach ($tpl->items as $i)
                        <tr><td>{{ $i->standard_name }} <span class="arah-muted">({{ $i->item_key }})</span></td><td>{{ $i->quantity }}</td><td>{{ $i->mode === 'quantity' ? 'kelompok' : 'individual' }}</td><td>{{ $i->positions ?: '—' }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="arah-muted" style="margin-top:.4rem">{{ $tpl->unitCount() }} unit individual per kamar. Seri, nilai, garansi, kondisi & foto <b>tidak</b> disalin — diisi saat verifikasi fisik.</p>
            @endif
        </div>
        <div class="arah-card">
            <h3 style="margin:0 0 .6rem">2 · Kamar ({{ count($roomIds) }} dipilih)</h3>
            <div style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center">
                <select class="ac-input" style="max-width:16rem" wire:model.live="floorId">
                    <option value="">Semua lantai / area</option>
                    @foreach ($this->floors as $f)<option value="{{ $f->id }}">{{ $f->full_path }}</option>@endforeach
                </select>
                <button type="button" class="arah-btn" wire:click="selectSameType">Pilih tipe sama</button>
                <button type="button" class="arah-btn" wire:click="clearRooms">Kosongkan</button>
            </div>
            <div class="arah-scroll" style="max-height:16rem; margin-top:.6rem; display:grid; grid-template-columns:repeat(auto-fill,minmax(8.5rem,1fr)); gap:.3rem">
                @forelse ($this->rooms as $r)
                    @php $same = $tpl && (int) $r->room_type_id === (int) $tpl->room_type_id; @endphp
                    <label style="display:flex; gap:.35rem; align-items:center; font-size:.85rem; {{ $tpl && ! $same ? 'opacity:.55' : '' }}">
                        <input type="checkbox" value="{{ $r->id }}" wire:model.live="roomIds">
                        <span>{{ $r->room_ref ?: $r->name }} <span class="arah-muted">{{ $r->roomType?->code }}</span></span>
                    </label>
                @empty
                    <div class="arah-empty">Belum ada kamar. Tambahkan di Lokasi.</div>
                @endforelse
            </div>
            <label style="display:flex; gap:.4rem; margin-top:.6rem; font-size:.85rem"><input type="checkbox" wire:model.live="allowMismatch"> Izinkan kamar berbeda tipe (pengecualian disetujui)</label>
            <div class="ac-actions"><button type="button" class="arah-btn arah-btn-primary" wire:click="runPreview" wire:loading.attr="disabled">Lihat preview</button></div>
        </div>
    </div>

    @if ($preview)
        <div class="arah-card" style="margin-top:1rem">
            <h3 style="margin:0 0 .6rem">3 · Preview — belum ada data yang dibuat</h3>
            <div class="arah-grid arah-grid-5">
                <div class="arah-stat"><span class="label">Kamar</span><span class="value">{{ $totals['rooms'] }}</span><span class="sub">{{ $totals['blocked'] }} terhalang tipe</span></div>
                <div class="arah-stat"><span class="label">Unit standar</span><span class="value">{{ $totals['standard'] }}</span></div>
                <div class="arah-stat"><span class="label">Sudah terpetakan</span><span class="value">{{ $totals['mapped'] }}</span></div>
                <div class="arah-stat"><span class="label">Kandidat cocok</span><span class="value">{{ $totals['candidate'] }}</span></div>
                <div class="arah-stat"><span class="label">Akan dibuat</span><span class="value">{{ $totals['create'] }}</span><span class="sub">+ {{ $totals['excess'] }} berlebih, {{ $totals['not_available'] }} n/a</span></div>
            </div>
            <div class="arah-scroll" style="margin-top:1rem; max-height:32rem">
                @foreach ($preview as $room)
                    <details {{ $loop->first || $room['counts']['candidate'] ? 'open' : '' }} style="margin-bottom:.6rem">
                        <summary style="cursor:pointer"><b>{{ $room['room_ref'] }}</b> <span class="arah-muted">{{ $room['room'] }}</span>
                            @unless ($room['allowed'])<span class="arah-badge arah-merah">tipe kamar berbeda — dilewati</span>@endunless
                            <span class="arah-muted">· {{ $room['counts']['create'] }} dibuat · {{ $room['counts']['candidate'] }} kandidat · {{ $room['counts']['mapped'] }} terpetakan</span></summary>
                        <table class="arah-table" style="margin-top:.3rem">
                            <thead><tr><th>Item</th><th>Posisi</th><th>Keadaan</th><th>Aset / keputusan</th><th>Catatan</th></tr></thead>
                            <tbody>
                            @foreach ($room['rows'] as $r)
                                @php $key = $room['room_id'].'|'.$r['item_key'].'|'.$r['unit_position']; @endphp
                                <tr>
                                    <td>{{ $r['name'] }}</td>
                                    <td>{{ $r['position_label'] ?? ($r['mode'] === 'quantity' ? $r['expected_qty'].' '.$r['unit'] : '—') }}</td>
                                    <td><span class="ac-state ac-state-{{ $r['state'] }}">{{ $stateLabel[$r['state']] }}</span></td>
                                    <td>
                                        @if ($r['state'] === 'mapped' || $r['state'] === 'excess')
                                            {{ $r['mapped_label'] }}
                                        @elseif ($r['state'] === 'candidate')
                                            <select class="ac-input" style="min-width:14rem" wire:model="decisions.{{ $key }}">
                                                <option value="">Buat unit baru (abaikan kandidat)</option>
                                                @foreach ($r['candidates'] as $c)<option value="{{ $c['id'] }}">Cocokkan: {{ $c['label'] }}</option>@endforeach
                                            </select>
                                        @elseif ($r['state'] === 'create')
                                            <span class="arah-muted">aset draft + label baru</span>
                                        @else — @endif
                                    </td>
                                    <td class="arah-muted">{{ $r['note'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </details>
                @endforeach
            </div>
            @unless ($batchId)
                <div class="ac-actions">
                    <button type="button" class="arah-btn arah-btn-primary" wire:click="commit" wire:loading.attr="disabled"
                        wire:confirm="Buat {{ $totals['create'] }} kandidat aset (draft, belum terverifikasi) dan cocokkan aset lama yang dipilih?">4 · Buat kandidat aset</button>
                    <span class="arah-muted" wire:loading wire:target="commit">memproses per kamar…</span>
                </div>
                <p class="arah-muted">Diproses per kamar secara atomik. Bila slot kamar berubah sejak preview, kamar itu ditandai konflik dan tidak diubah. Mengirim ulang tidak membuat aset ganda.</p>
            @endunless
        </div>
    @endif

    @if ($b = $this->batch)
        <div class="arah-card" style="margin-top:1rem">
            <h3 style="margin:0 0 .6rem">Hasil batch {{ $b->number }} · {{ GenerationBatch::STATUSES[$b->status] }}</h3>
            <p>{{ $b->created_assets }} kandidat dibuat · {{ $b->matched_assets }} aset lama dicocokkan · {{ $b->results->where('status', '!=', 'ok')->count() }} kamar perlu perhatian.</p>
            <div class="ac-actions">
                <a class="arah-btn arah-btn-primary" href="{{ \App\Filament\Pages\CetakLabel::getUrl(['batch' => $b->id]) }}">5 · Cetak label batch ini</a>
                <a class="arah-btn" href="{{ $batchUrl }}">Detail & retry kamar gagal</a>
                <a class="arah-btn" href="{{ \App\Filament\Resources\Assets\AssetResource::getUrl('index', ['tab' => 'draft']) }}">Daftar kandidat draft</a>
            </div>
        </div>
    @endif
</div>
</x-filament-panels::page>
