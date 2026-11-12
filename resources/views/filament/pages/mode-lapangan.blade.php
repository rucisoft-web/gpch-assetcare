<x-filament-panels::page>
@php
    $asset = $this->getAsset();
    $location = $this->getLocation();
    $write = $this->canWrite();
@endphp
<div class="rac-lap">

    {{-- 1. Pindai --}}
    <section class="rac-lap-card"
        x-data="racScanner($wire)"
        x-on:lapangan-focus.window="$nextTick(() => $refs.scan?.focus())">
        <h3>Pindai QR aset</h3>
        <form wire:submit="submitScan" class="rac-lap-scan">
            <input x-ref="scan" type="text" wire:model="scan" autofocus autocomplete="off" inputmode="none"
                placeholder="Tekan tombol scan di handheld, atau ketik kode aset">
            <button type="submit" class="rac-lap-btn">Cari</button>
        </form>
        <div class="rac-lap-row">
            <button type="button" class="rac-lap-btn rac-lap-primary" x-on:click="toggle()" x-text="running ? 'Tutup kamera' : 'Pindai dengan kamera'"></button>
        </div>
        <div wire:ignore>
            <div id="rac-lap-reader" x-show="running" class="rac-lap-reader"></div>
        </div>
        <p class="rac-muted" x-show="error" x-text="error"></p>
        <p class="rac-muted">Handheld dengan scanner bawaan: biarkan kursor di kotak di atas lalu tekan tombol scan. Kamera HP butuh situs HTTPS.</p>
    </section>

    {{-- 2. Aset hasil pindai --}}
    @if ($asset)
        <section class="rac-lap-card rac-lap-asset">
            <div class="rac-lap-head">
                <div>
                    <div class="rac-muted">{{ $asset->code }} · {{ $asset->category?->name }}</div>
                    <h2>{{ $asset->name }}</h2>
                    <div class="rac-muted">{{ $asset->location?->complete_name }}</div>
                </div>
                <button type="button" class="rac-lap-x" wire:click="closeAsset" title="Tutup">×</button>
            </div>
            <div class="rac-lap-badges">
                <span class="rac-lap-badge">Kesehatan {{ number_format($asset->health_score, 0) }}</span>
                <span class="rac-lap-badge">{{ $asset->condition->getLabel() }}</span>
                <span class="rac-lap-badge">{{ $asset->status->getLabel() }}</span>
                <span class="rac-lap-badge rac-lap-v-{{ $asset->verification_status->value }}">{{ $asset->verification_status->getLabel() }}</span>
            </div>
            @if ($asset->brand || $asset->serial_no)
                <div class="rac-muted">{{ $asset->brand }} {{ $asset->model }} @if($asset->serial_no) · SN {{ $asset->serial_no }} @endif</div>
            @endif
            @if ($asset->note)
                <div class="rac-muted">{{ $asset->note }}</div>
            @endif

            @if ($write)
                <div class="rac-lap-grid">
                    <button type="button" class="rac-lap-btn rac-lap-ok" wire:click="mark({{ $asset->id }}, 'verified')">✓ Ada</button>
                    <button type="button" class="rac-lap-btn rac-lap-bad" wire:click="mark({{ $asset->id }}, 'missing')">✗ Tidak ada</button>
                </div>
                <div class="rac-lap-grid">
                    {{ $this->inspectAction }}
                    {{ $this->reportAction }}
                </div>
            @endif
            <div class="rac-lap-grid">
                <a class="rac-lap-btn" target="_blank" href="{{ $this->labelUrl(['ids' => $asset->id]) }}">Cetak label</a>
                <a class="rac-lap-btn" href="{{ $this->assetUrl($asset) }}">Detail lengkap</a>
            </div>
        </section>
    @endif

    {{-- 3. Keliling per lokasi --}}
    <section class="rac-lap-card">
        <h3>Keliling per lokasi</h3>
        <select wire:model.live="locationId" class="rac-lap-select">
            <option value="">— Pilih lokasi —</option>
            @foreach ($this->getLocationOptions() as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>

        @if ($location)
            @if ($location->parent_id)
                <button type="button" class="rac-lap-link" wire:click="chooseLocation({{ $location->parent_id }})">← naik ke induk</button>
            @endif

            @php $children = $this->getChildLocations(); @endphp
            @if ($children->isNotEmpty())
                <div class="rac-lap-chips">
                    @foreach ($children as $child)
                        <button type="button" class="rac-lap-chip" wire:click="chooseLocation({{ $child->id }})">{{ $child->name }} <small>{{ $child->assets_count }}</small></button>
                    @endforeach
                </div>
            @endif

            @php $items = $this->getLocationAssets(); @endphp
            <div class="rac-lap-row">
                <span class="rac-muted">{{ $items->count() }} aset · {{ $items->where('verification_status', \App\Enums\VerificationStatus::Verified)->count() }} terverifikasi</span>
            </div>
            <ul class="rac-lap-list">
                @forelse ($items as $item)
                    <li wire:key="lap-{{ $item->id }}">
                        <button type="button" class="rac-lap-item" wire:click="openAsset({{ $item->id }})">
                            <b>{{ $item->name }}</b>
                            <small>{{ $item->code }} · <span class="rac-lap-v-{{ $item->verification_status->value }}">{{ $item->verification_status->getLabel() }}</span></small>
                        </button>
                        @if ($write)
                            <span class="rac-lap-quick">
                                <button type="button" title="Ada" class="rac-lap-ok" wire:click="mark({{ $item->id }}, 'verified')">✓</button>
                                <button type="button" title="Tidak ada" class="rac-lap-bad" wire:click="mark({{ $item->id }}, 'missing')">✗</button>
                            </span>
                        @endif
                    </li>
                @empty
                    <li class="rac-muted">Belum ada aset di lokasi ini.</li>
                @endforelse
            </ul>

            <div class="rac-lap-grid">
                {{ $this->addAssetAction }}
                @if ($items->isNotEmpty())
                    <a class="rac-lap-btn" target="_blank" href="{{ $this->labelUrl(['location' => $location->id]) }}">Cetak label lokasi ini</a>
                @endif
            </div>
        @endif
    </section>

    {{-- 4. Ukuran label --}}
    <section class="rac-lap-card">
        <h3>Ukuran label</h3>
        <select wire:model.live="labelSize" class="rac-lap-select">
            @foreach (\App\Support\LabelSize::options() as $key => $text)
                <option value="{{ $key }}">{{ $text }}</option>
            @endforeach
        </select>
        <p class="rac-muted">Dipakai untuk tombol cetak di halaman ini. Ukuran juga bisa diganti di halaman label sebelum mencetak.</p>
    </section>
</div>

<script>
    window.racScanner = window.racScanner || function ($wire) {
        return {
            running: false, error: '', scanner: null,
            load() {
                if (window.Html5Qrcode) return Promise.resolve();
                return new Promise((resolve, reject) => {
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
                    s.onload = resolve; s.onerror = () => reject(new Error('Pustaka pemindai gagal dimuat (periksa koneksi internet).'));
                    document.head.appendChild(s);
                });
            },
            async toggle() {
                if (this.running) return this.stop();
                this.error = '';
                try {
                    await this.load();
                    this.running = true;
                    await this.$nextTick();
                    this.scanner = new Html5Qrcode('rac-lap-reader');
                    await this.scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, (text) => {
                        this.stop();
                        $wire.set('scan', text, false);
                        $wire.submitScan();
                    });
                } catch (e) {
                    this.running = false;
                    this.error = 'Kamera tidak bisa dibuka: ' + (e?.message || e) + '. Pastikan izin kamera diberikan dan situs memakai HTTPS.';
                }
            },
            async stop() {
                try { await this.scanner?.stop(); this.scanner?.clear(); } catch (e) {}
                this.running = false;
            },
        };
    };
</script>
</x-filament-panels::page>
