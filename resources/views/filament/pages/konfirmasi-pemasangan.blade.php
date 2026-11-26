@php
    use App\Models\InstallationEvent;
    $expected = $this->expected;
    $installed = $expected->filter(fn ($l) => $l->currentInstallationStatus() === 'installed')->count();
@endphp
<x-filament-panels::page>
<div class="arah">
    <div class="arah-grid arah-grid-2">
        <div class="arah-card">
            <h3 style="margin:0 0 .5rem">1 · Pilih kamar / ruang</h3>
            <select class="ac-input" wire:model.live="roomId">
                <option value="">— pilih —</option>
                @foreach ($this->rooms as $r)<option value="{{ $r->id }}">{{ $r->labelText() }} — {{ $r->full_path }}</option>@endforeach
            </select>
            @if ($this->room)
                <h3 style="margin:1rem 0 .5rem">2 · Pindai label yang sudah ditempel</h3>
                <x-ac.scanner method="confirmInstall" hint="Pindai label satu per satu setelah ditempel" />
            @endif
            @foreach ($feed as $f)
                <div class="ac-scan-msg {{ $f['level'] === 'danger' || $f['level'] === 'warning' ? 'err' : 'ok' }}"><span class="arah-muted">{{ $f['at'] }}</span> {{ $f['message'] }}</div>
            @endforeach
        </div>
        <div class="arah-card">
            <h3 style="margin:0 0 .5rem">Label yang seharusnya ada @if($this->room)<span class="arah-muted">· {{ $installed }}/{{ $expected->count() }} terpasang</span>@endif</h3>
            @if (! $this->room)
                <div class="arah-empty">Pilih kamar untuk melihat daftar label.</div>
            @elseif ($expected->isEmpty())
                <div class="arah-empty">Belum ada label aktif untuk ruang ini.</div>
            @else
                <table class="arah-table">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($expected as $l)
                        @php $st = $l->currentInstallationStatus(); @endphp
                        <tr wire:key="lbl-{{ $l->id }}">
                            <td><b>{{ $l->asset?->asset_code ?? $l->quantityGroup?->group_code }}</b></td>
                            <td>{{ $l->asset?->name ?? $l->quantityGroup?->name }}</td>
                            <td><span class="arah-badge {{ $st === 'installed' ? 'arah-hijau' : ($st === 'pending' ? 'arah-abu' : 'arah-merah') }}">{{ InstallationEvent::STATUSES[$st] ?? $st }}</span></td>
                            <td style="white-space:nowrap">
                                @if ($st !== 'installed')
                                    <button type="button" class="arah-btn" style="padding:.2rem .5rem; font-size:.75rem" wire:click="mark({{ $l->id }}, 'damaged')">Rusak</button>
                                    <button type="button" class="arah-btn" style="padding:.2rem .5rem; font-size:.75rem" wire:click="mark({{ $l->id }}, 'missing')">Hilang</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
</x-filament-panels::page>
