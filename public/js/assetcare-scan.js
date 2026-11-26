/*
 * Pemindai QR Asset Care GPCH.
 * 1) Kamera langsung: BarcodeDetector (Chrome Android) atau jsQR (fallback). Butuh HTTPS atau localhost.
 * 2) Foto: <input capture> — berfungsi juga di HTTP jaringan lokal.
 * 3) Input manual / scanner USB (keyboard wedge).
 * Hasil dikirim ke metode Livewire yang ditentukan halaman (resolve / confirmInstall).
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('acScanner', (method) => ({
        method,
        stream: null,
        running: false,
        busy: false,
        last: '',
        lastAt: 0,
        message: '',
        detector: null,
        secure: window.isSecureContext,
        canCamera: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && window.isSecureContext,
        init() {
            if ('BarcodeDetector' in window) {
                try { this.detector = new window.BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { this.detector = null; }
            }
        },
        async start() {
            this.message = '';
            if (!this.canCamera) {
                this.message = 'Kamera langsung memerlukan HTTPS. Gunakan tombol "Foto label" atau ketik kode.';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }, audio: false });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.running = true;
                this.loop();
            } catch (e) {
                this.message = 'Kamera tidak dapat dibuka (' + (e.name || 'error') + '). Izinkan akses kamera atau gunakan "Foto label".';
            }
        },
        stop() {
            this.running = false;
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
        },
        async loop() {
            if (!this.running) return;
            const v = this.$refs.video;
            if (v.readyState >= 2 && !this.busy) {
                const text = await this.decodeSource(v, v.videoWidth, v.videoHeight);
                if (text) this.found(text, 'scanner');
            }
            setTimeout(() => this.loop(), 180);
        },
        async decodeSource(src, w, h) {
            if (!w || !h) return null;
            if (this.detector) {
                try {
                    const codes = await this.detector.detect(src);
                    if (codes.length) return codes[0].rawValue;
                } catch (e) { /* lanjut ke jsQR */ }
            }
            if (!window.jsQR) return null;
            const scale = Math.min(1, 900 / Math.max(w, h));
            const c = this.$refs.canvas;
            c.width = Math.round(w * scale); c.height = Math.round(h * scale);
            const ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(src, 0, 0, c.width, c.height);
            const img = ctx.getImageData(0, 0, c.width, c.height);
            const r = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' });
            return r ? r.data : null;
        },
        async fromFile(ev) {
            const f = ev.target.files && ev.target.files[0];
            if (!f) return;
            this.message = 'Membaca foto…';
            const url = URL.createObjectURL(f);
            const img = new Image();
            img.onload = async () => {
                const text = await this.decodeSource(img, img.naturalWidth, img.naturalHeight);
                URL.revokeObjectURL(url);
                ev.target.value = '';
                if (text) { this.message = ''; this.found(text, 'scanner'); }
                else this.message = 'QR tidak terbaca. Dekatkan kamera (±15 cm), hindari pantulan, atau ketik kode aset.';
            };
            img.src = url;
        },
        submitManual() {
            const v = (this.$refs.manual.value || '').trim();
            if (v) { this.found(v, 'manual'); this.$refs.manual.value = ''; }
        },
        async found(text, channel) {
            const now = Date.now();
            if (text === this.last && now - this.lastAt < 2500) return; // cegah pemindaian ganda
            this.last = text; this.lastAt = now; this.busy = true;
            if (navigator.vibrate) navigator.vibrate(60);
            try { await this.$wire.call(this.method, text, channel); }
            finally { this.busy = false; }
        },
        destroy() { this.stop(); },
    }));
});
