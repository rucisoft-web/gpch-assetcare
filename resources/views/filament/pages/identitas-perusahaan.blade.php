<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <div style="display:flex; gap:.6rem; align-items:center">
            <x-filament::button type="submit" icon="heroicon-o-check">Simpan</x-filament::button>
            <span class="arah-muted">Pratinjau: <b>{{ \App\Support\Brand::panelName() }}</b> · {{ \App\Support\Brand::contactLine() ?: 'kontak belum diisi' }}</span>
        </div>
    </form>
</x-filament-panels::page>
