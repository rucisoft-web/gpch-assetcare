/* Perkecil foto di ponsel sebelum diunggah: apa pun formatnya menjadi JPEG < 500 KB (maks. 1600 px).
   Dipakai oleh <input type="file" data-compress>. Bila browser tidak mampu, berkas asli dikirim dan server yang memperkecil. */
(function () {
  const MAX_BYTES = 500 * 1024, MAX_SIDE = 1600;
  const toBlob = (c, q) => new Promise(r => c.toBlob(r, 'image/jpeg', q));
  async function load(file) {
    if (window.createImageBitmap) {
      try { return await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch (e) { /* lanjut ke <img> */ }
    }
    return await new Promise((ok, no) => { const i = new Image(); i.onload = () => ok(i); i.onerror = no; i.src = URL.createObjectURL(file); });
  }
  async function shrink(file) {
    if (!/^image\//.test(file.type) && !/\.(heic|heif|jpe?g|png|webp|gif|bmp)$/i.test(file.name)) return file;
    const img = await load(file);
    const w = img.width || img.naturalWidth, h = img.height || img.naturalHeight;
    let side = Math.min(MAX_SIDE, Math.max(w, h)), blob = null;
    for (let round = 0; round < 8; round++) {
      const s = side / Math.max(w, h), c = document.createElement('canvas');
      c.width = Math.max(1, Math.round(w * s)); c.height = Math.max(1, Math.round(h * s));
      const g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); g.drawImage(img, 0, 0, c.width, c.height);
      for (const q of [0.82, 0.72, 0.62, 0.52]) { blob = await toBlob(c, q); if (blob && blob.size <= MAX_BYTES) break; }
      if (blob && blob.size <= MAX_BYTES) break;
      side = Math.round(side * 0.8);
    }
    if (!blob) return file;
    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
  }
  const kb = n => Math.round(n / 1024) + ' KB';
  document.querySelectorAll('input[type=file][data-compress]').forEach(input => {
    const note = document.createElement('div'); note.className = 'muted'; note.style.marginTop = '6px';
    (input.closest('label') || input).insertAdjacentElement('afterend', note);
    input.addEventListener('change', () => {
      const files = Array.from(input.files || []); if (!files.length || !window.DataTransfer) return;
      note.textContent = 'Memperkecil foto…';
      input._job = Promise.all(files.map(f => shrink(f).catch(() => f))).then(out => {
        const dt = new DataTransfer(); out.forEach(f => dt.items.add(f)); input.files = dt.files;
        const before = files.reduce((a, f) => a + f.size, 0), after = out.reduce((a, f) => a + f.size, 0);
        note.textContent = out.length + ' foto siap (' + kb(before) + ' → ' + kb(after) + ')';
      }).finally(() => { input._job = null; });
    });
    const form = input.form;
    if (form && !form._compressHook) {
      form._compressHook = true;
      form.addEventListener('submit', e => {
        const jobs = Array.from(form.querySelectorAll('input[data-compress]')).map(i => i._job).filter(Boolean);
        if (!jobs.length) return;
        e.preventDefault(); e.stopImmediatePropagation();
        Promise.all(jobs).then(() => form.submit());
      }, true);
    }
  });
})();
