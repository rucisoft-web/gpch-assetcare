<x-filament-panels::page>
    <div class="arah">
        <div class="arah-card" style="padding:1rem 1.2rem">
            <p style="margin:0 0 .6rem">Backup berisi <b>seluruh database</b> dalam satu berkas <code>.sql</code> bertanggal, dikemas ZIP. Backup otomatis dibuat setiap hari pukul 01:30; backup lebih dari {{ \App\Services\BackupService::KEEP_DAYS }} hari dihapus otomatis (5 terbaru selalu disimpan).</p>
            <p class="arah-muted" style="margin:0 0 .8rem">Simpan unduhan di tempat aman di luar server (mis. NAS / drive IT). Berkas ini memuat data pengguna, jangan dibagikan. Foto & dokumen lampiran tidak termasuk; lampiran dicadangkan terpisah oleh skrip server.</p>
            <x-filament::button wire:click="create" wire:loading.attr="disabled" icon="heroicon-o-arrow-down-on-square-stack">
                <span wire:loading.remove wire:target="create">Buat backup sekarang</span>
                <span wire:loading wire:target="create">Membuat backup…</span>
            </x-filament::button>
        </div>

        <div class="arah-card" style="padding:0; margin-top:1rem; overflow-x:auto">
            <table class="arah-table" style="width:100%">
                <thead><tr><th>Tanggal backup</th><th>Berkas</th><th style="text-align:right">Ukuran</th><th></th></tr></thead>
                <tbody>
                @forelse ($this->backups as $b)
                    <tr wire:key="{{ $b['name'] }}">
                        <td>{{ $b['time']->translatedFormat('d M Y, H:i') }}</td>
                        <td><code>{{ $b['name'] }}</code></td>
                        <td style="text-align:right">{{ $b['size'] >= 1048576 ? number_format($b['size'] / 1048576, 1, ',', '.').' MB' : number_format($b['size'] / 1024, 0, ',', '.').' KB' }}</td>
                        <td style="text-align:right; white-space:nowrap">
                            <x-filament::button size="sm" wire:click="download('{{ $b['name'] }}')" icon="heroicon-o-arrow-down-tray">Unduh</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="remove('{{ $b['name'] }}')" wire:confirm="Hapus backup {{ $b['name'] }}?" icon="heroicon-o-trash">Hapus</x-filament::button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="arah-muted">Belum ada backup. Klik "Buat backup sekarang".</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
