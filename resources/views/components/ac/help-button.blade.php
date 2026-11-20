@php $key = app(\App\Support\Help\HelpService::class)->keyFor(request()->route()?->getName()); @endphp
<div x-data="{ open: false, loaded: false, html: '', async show() { this.open = true; if (!this.loaded) { try { const r = await fetch('{{ route('help.show', $key) }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); this.html = r.ok ? await r.text() : '<p>Bantuan untuk halaman ini belum tersedia.</p>'; } catch (e) { this.html = '<p>Gagal memuat bantuan. Periksa koneksi.</p>'; } this.loaded = true; } } }"
     x-on:keydown.escape.window="open = false" x-on:open-help.window="show()" class="ac-help">
    <button type="button" x-on:click="show()" class="ac-help-btn" title="Bantuan halaman ini" aria-label="Bantuan">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/></svg>
        <span>Bantuan</span>
    </button>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="ac-help-layer" x-transition.opacity>
            <div class="ac-help-backdrop" x-on:click="open = false"></div>
            <aside class="ac-help-panel" role="dialog" aria-label="Bantuan" x-show="open" x-transition:enter="ac-help-in" x-transition:leave="ac-help-out">
                <div class="ac-help-head">
                    <span>Bantuan</span>
                    <a href="{{ \App\Filament\Pages\PanduanPeran::getUrl() }}" class="ac-help-role">Panduan per peran ›</a>
                    <button type="button" x-on:click="open = false" aria-label="Tutup">✕</button>
                </div>
                <div class="ac-help-scroll">
                    <div x-show="!loaded" class="arah-muted" style="padding:1rem">Memuat…</div>
                    <div x-html="html"></div>
                </div>
            </aside>
        </div>
    </template>
</div>
