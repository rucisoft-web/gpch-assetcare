<x-filament-panels::page>
<div class="arah">
    <div class="arah-card">
        <x-ac.scanner method="resolve" />
        @if ($message)
            <div class="ac-scan-msg {{ $level === 'danger' ? 'err' : 'ok' }}" style="max-width:520px; margin:.8rem auto 0">{{ $message }}</div>
        @endif
    </div>
    @if ($this->history->isNotEmpty())
        <div class="arah-card" style="margin-top:1rem">
            <h3 style="margin:0 0 .5rem">Riwayat scan Anda (12 jam)</h3>
            <ul class="arah-list">
                @foreach ($this->history as $h)
                    <li>
                        <span class="arah-muted">{{ \App\Support\Fmt::dt($h->scanned_at, 'H:i') }}</span>
                        @if ($h->asset)
                            <a href="{{ \App\Filament\Resources\Assets\AssetResource::getUrl('view', ['record' => $h->asset]) }}" style="color:var(--k-link)"><b>{{ $h->asset->asset_code }}</b> {{ $h->asset->name }}</a>
                            <span class="arah-muted">· {{ $h->asset->location?->labelText() }}</span>
                        @else
                            <span>{{ ['revoked' => 'Label tidak berlaku', 'unknown' => 'Tidak dikenal', 'foreign' => 'QR asing', 'group' => 'Kelompok jumlah', 'forbidden' => 'Tanpa akses'][$h->result] ?? $h->result }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
</x-filament-panels::page>
