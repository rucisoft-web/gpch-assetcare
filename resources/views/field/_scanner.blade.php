{{-- Pemindai QR: kamera ponsel ATAU handheld scanner (mode keyboard). Halaman pemakai mendefinisikan window.fieldOnScan(teks). --}}
<div class="card" style="padding:10px 12px;display:flex;align-items:center;gap:10px">
    <span style="flex:1;font-size:14px"><b>Pakai handheld scanner</b><br><span class="muted" style="font-size:13px">Kamera dimatikan; tekan tombol scan di handheld.</span></span>
    <label style="margin:0;position:relative;display:inline-block;width:52px;height:30px">
        <input type="checkbox" id="hh" style="opacity:0;width:0;height:0">
        <span id="hh-ui" style="position:absolute;inset:0;border-radius:999px;background:#D6CCB8;transition:.2s"></span>
        <span id="hh-dot" style="position:absolute;top:3px;left:3px;width:24px;height:24px;border-radius:50%;background:#fff;transition:.2s"></span>
    </label>
</div>
<div id="hh-ready" class="card" style="display:none;text-align:center;border:2px dashed var(--hijau)">
    <div style="font-size:34px;line-height:1">⌁</div>
    <b>Siap menerima scan dari handheld</b>
    <p class="muted" style="margin:6px 0 0">Arahkan handheld ke label, tekan tombol scan. Hasil masuk otomatis.</p>
    <input id="hh-in" type="text" inputmode="none" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="hasil scan masuk di sini" style="margin-top:10px;text-align:center">
    <p id="hh-last" class="muted" style="margin:6px 0 0"></p>
</div>
<div id="cam" class="card" style="padding:10px">
    <div style="position:relative;border-radius:12px;overflow:hidden;background:#111;aspect-ratio:{{ $ratio ?? '3/4' }}">
        <video id="v" playsinline muted style="width:100%;height:100%;object-fit:cover"></video>
        <div style="position:absolute;inset:18% 12%;border:3px solid rgba(255,255,255,.85);border-radius:16px"></div>
    </div>
    <canvas id="c" hidden></canvas>
    <p id="msg" class="muted" style="text-align:center;margin:10px 0 0">Membuka kamera…</p>
</div>
<label id="cam-file" class="btn alt" style="margin:0 0 10px;font-weight:600">Foto label (bila kamera tidak terbuka)<input id="file" type="file" accept="image/*" capture="environment" hidden></label>
@push('scripts')
<script src="{{ asset('js/vendor/jsQR.js') }}?v=1.4.0"></script>
<script>
(function () {
  const v = document.getElementById('v'), c = document.getElementById('c'), msg = document.getElementById('msg');
  let busy = false;
  const go = (t) => { t = (t || '').trim(); if (!t || busy) return; busy = true; if (navigator.vibrate) navigator.vibrate(60); window.fieldOnScan(t); };
  let det = null; if ('BarcodeDetector' in window) { try { det = new BarcodeDetector({ formats: ['qr_code'] }); } catch (e) {} }
  async function decode(src, w, h) {
    if (!w || !h) return null;
    if (det) { try { const r = await det.detect(src); if (r.length) return r[0].rawValue; } catch (e) {} }
    if (!window.jsQR) return null;
    const s = Math.min(1, 900 / Math.max(w, h)); c.width = Math.round(w * s); c.height = Math.round(h * s);
    const x = c.getContext('2d', { willReadFrequently: true }); x.drawImage(src, 0, 0, c.width, c.height);
    const d = x.getImageData(0, 0, c.width, c.height); const r = jsQR(d.data, d.width, d.height, { inversionAttempts: 'attemptBoth' });
    return r ? r.data : null;
  }
  let stream = null, running = false;
  async function loop() { if (!running) return; if (v.readyState >= 2) { const t = await decode(v, v.videoWidth, v.videoHeight); if (t) { running = false; msg.textContent = 'Terbaca, membuka…'; return go(t); } } setTimeout(loop, 200); }
  function startCam() {
    if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.isSecureContext)) { msg.textContent = 'Kamera langsung butuh HTTPS. Pakai tombol "Foto label".'; return; }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
      .then(s => { stream = s; v.srcObject = s; v.play(); running = true; msg.textContent = 'Arahkan kotak ke label QR (±15 cm).'; loop(); })
      .catch(e => { msg.textContent = 'Kamera tidak bisa dibuka. Izinkan akses kamera, atau pakai "Foto label".'; });
  }
  function stopCam() { running = false; if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; } }

  // Handheld scanner (mode keyboard / "keyboard wedge"): mengetik isi QR lalu Enter. Ditangkap di mana pun fokusnya,
  // kecuali saat pengguna sedang mengetik di kolom isian.
  const hh = document.getElementById('hh'), ui = document.getElementById('hh-ui'), dot = document.getElementById('hh-dot');
  const ready = document.getElementById('hh-ready'), cam = document.getElementById('cam'), camFile = document.getElementById('cam-file');
  let buf = '', last = 0;
  document.addEventListener('keydown', (e) => {
    const el = document.activeElement, typing = el && /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) && el.id !== 'hh';  // termasuk #hh-in (ditangani sendiri)
    if (typing) return;
    const now = Date.now(); if (now - last > 120) buf = ''; last = now;
    if (e.key === 'Enter') { if (buf.length >= 4) { e.preventDefault(); document.getElementById('hh-last').textContent = 'Terbaca: ' + buf.slice(0, 60); go(buf); } buf = ''; return; }
    if (e.key.length === 1) buf += e.key;
  });
  // Banyak handheld Android mengisi kolom yang sedang fokus (dengan/tanpa Enter). Kolom ini fokus otomatis; inputmode=none agar keyboard layar tidak muncul.
  const hin = document.getElementById('hh-in'); let tmr = null;
  hin.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); clearTimeout(tmr); go(hin.value); } });
  hin.addEventListener('input', () => { clearTimeout(tmr); tmr = setTimeout(() => { if (hin.value.trim().length >= 8) go(hin.value); }, 350); });
  document.addEventListener('visibilitychange', () => { if (!document.hidden && hh.checked) hin.focus(); });
  function mode(on) {
    try { localStorage.setItem('ac-handheld', on ? '1' : '0'); } catch (e) {}
    hh.checked = on; ui.style.background = on ? 'var(--hijau)' : '#D6CCB8'; dot.style.left = on ? '25px' : '3px';
    ready.style.display = on ? '' : 'none'; cam.style.display = on ? 'none' : ''; camFile.style.display = on ? 'none' : '';
    if (on) { stopCam(); hin.value = ''; setTimeout(() => hin.focus(), 50); } else { startCam(); }
  }
  hh.addEventListener('change', () => mode(hh.checked));
  let saved = '0'; try { saved = localStorage.getItem('ac-handheld') || '0'; } catch (e) {}
  mode(saved === '1');

  document.getElementById('file').addEventListener('change', (ev) => {
    const f = ev.target.files[0]; if (!f) return; msg.textContent = 'Membaca foto…';
    const img = new Image(); const u = URL.createObjectURL(f);
    img.onload = async () => { const t = await decode(img, img.naturalWidth, img.naturalHeight); URL.revokeObjectURL(u); if (t) go(t); else msg.textContent = 'QR tidak terbaca. Dekatkan kamera dan hindari pantulan.'; };
    img.src = u;
  });
})();
</script>
@endpush
