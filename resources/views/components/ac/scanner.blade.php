@props(['method' => 'resolve', 'hint' => 'Arahkan kamera ke label QR'])
<div x-data="acScanner(@js($method))" x-on:beforeunload.window="stop()" class="ac-scan-wrap">
    <div class="ac-scan-video" x-show="running" x-cloak>
        <video x-ref="video" playsinline muted></video>
        <div class="ac-scan-frame"></div>
    </div>
    <canvas x-ref="canvas" style="display:none"></canvas>
    <div class="ac-actions" style="justify-content:center">
        <button type="button" class="arah-btn arah-btn-primary" x-show="!running" x-on:click="start()">Mulai kamera</button>
        <button type="button" class="arah-btn" x-show="running" x-cloak x-on:click="stop()">Hentikan kamera</button>
        <label class="arah-btn" style="cursor:pointer">Foto label
            <input type="file" accept="image/*" capture="environment" style="display:none" x-on:change="fromFile($event)">
        </label>
    </div>
    <p class="arah-muted" style="text-align:center" x-show="running" x-cloak>{{ $hint }}</p>
    <p class="arah-muted" style="text-align:center" x-show="!secure" x-cloak>Mode jaringan lokal (HTTP): gunakan "Foto label". Kamera langsung aktif bila aplikasi dibuka lewat HTTPS.</p>
    <div class="ac-scan-msg err" x-show="message" x-text="message" x-cloak></div>
    <form x-on:submit.prevent="submitManual()" style="display:flex; gap:.5rem; margin-top:.8rem">
        <input x-ref="manual" class="ac-input" placeholder="Ketik kode aset (mis. MB-08-819-TV-01) atau gunakan scanner USB" autocomplete="off" inputmode="text">
        <button type="submit" class="arah-btn">Cari</button>
    </form>
</div>
