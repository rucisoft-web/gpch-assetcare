# Asset Care

Aplikasi pengelolaan aset & pemeliharaan — **Laravel 13 · Filament 5 · MySQL 8**. Nama aplikasi generik **Asset Care**; nama, logo, alamat, kontak, warna utama, dan ornamen perusahaan diatur di **Pengaturan › Identitas perusahaan** (instalasi ini terisi identitas Grand Pasundan Convention Hotel, tema Arah GPCH).

Instalasi: lihat **PANDUAN-LARAGON.md** (cukup jalankan `setup-laragon.bat`).

## Isi Rilis A (sudah dibuat)

| Modul spesifikasi | Menu | Isi |
|---|---|---|
| M01 Lokasi & referensi | Pengaturan, Lokasi & kamar | Gedung › lantai › kamar/ruang, tipe kamar, kategori (~50), departemen, vendor, kebijakan SLA P1–P4, batas persetujuan |
| M02 Register aset | Aset › Register aset | Kode permanen `GPCH-AST-000001`, kartu aset, verifikasi fisik, aktivasi (field wajib), komponen, riwayat status, dokumen privat, kelompok jumlah |
| M03 Label QR | Pengendalian Aset | Token acak (hanya hash tersimpan), cetak massal PDF ukuran aktual (A4 50×30, A4 75×50, roll), mulai sel, cetak ulang beralasan, rotasi token, konfirmasi pemasangan per kamar, Scan QR (kamera / foto / ketik kode) |
| M04 Laporan kerusakan | Pemeliharaan › Laporan kerusakan | Form cepat dari scan QR, saran prioritas, anti-tiket ganda, triase dengan alasan, gabung/tautkan, tolak/duplikat |
| M05 Work order | Pemeliharaan › Work order | Status terkontrol (draft → persetujuan → tugas → dikerjakan → selesai → verifikasi → tutup, hold/batal/buka ulang), batas persetujuan, pembuat ≠ penyetuju, pekerjaan kritis diverifikasi orang lain, izin kerja, garansi, jam kerja, biaya sementara/final + reversal, foto bukti, cek versi (409) |
| SLA & notifikasi | Lonceng notifikasi | Respons & pemulihan, kalender 24 jam / jam kerja, hold tidak dihitung, peringatan 80% & terlewat (`assetcare:sla-check`) |
| Template & generate massal | Template aset kamar, Generate aset kamar | Template berversi (penyusun ≠ pemeriksa), preview selisih, pencocokan aset lama, kandidat draft, idempoten, konflik per kamar, retry |
| Mutasi aset | Aset › Mutasi aset | Ajukan → setujui → kirim → terima (kondisi) → selesai; slot kamar dilepas, label ditandai perlu cetak ulang |
| Blokir kamar | Pemeliharaan › Blokir kamar | Usulan Engineering, konfirmasi/lepas oleh Front Office (PMS tetap manual) |
| M08 Laporan | Laporan & KPI | Backlog & umur, kepatuhan SLA, MTTR bruto/bersih, kegagalan berulang, downtime, register, status label, biaya, penyusutan komersial, daftar fiskal per kelompok, rekonsiliasi & pajak tangguhan, mutasi aset tetap, cek kapitalisasi (khusus izin keuangan), kekurangan dokumen wajib, dokumen akan berakhir, aset tanpa polis, kepatuhan PM, jadwal PM 60 hari, kondisi di luar batas, riwayat pemeliharaan per aset; ekspor CSV aman |
| Identitas perusahaan | Pengaturan › Identitas perusahaan | Nama & nama singkat ("Asset Care · GPCH"), badan hukum, NPWP, alamat, kontak, logo terang/gelap, favicon, warna utama, judul label QR, ornamen wayang on/off (izin `settings.manage`) |
| Peta aset | Aset › Peta aset | Pohon lokasi yang bisa diklik (jumlah aset termasuk sub-lokasi), daftar aset per lokasi, sebaran per jenis aset (kelas kebijakan › kategori → di lokasi mana saja), pencarian, total biaya perolehan |
| Preventive maintenance | Pemeliharaan › Rencana PM | Rencana per aset atau per kategori, interval hari dan/atau meter (jam operasi), checklist, H-lead; WO preventive/inspeksi dibuat otomatis harian 06:00 (`assetcare:pm-generate`), satu WO terbuka per jadwal; jadwal berikutnya dihitung dari tanggal selesai setelah diverifikasi; buat rencana cepat dari interval PM bawaan kategori |
| Predictive maintenance | Pemeliharaan › Pemantauan kondisi, Parameter kondisi & meter | 8 parameter bawaan (jam operasi, suhu chiller/freezer, tekanan boiler, getaran ISO 10816, suhu panel, tekanan APAR, aki genset) dengan batas waspada/kritis; pembacaan kritis → WO predictive otomatis (tidak dobel), meter tidak boleh turun |
| Riwayat pemeliharaan | Kartu aset › Riwayat pemeliharaan, Jadwal PM, Kondisi & meter | Insidental (korektif & darurat) vs preventive/inspeksi vs predictive, biaya per WO, PM terakhir |
| Kebijakan aset tetap (draf Nov 2026) | Aset Tetap & Dokumen › Kelas aset kebijakan, Kelompok harta fiskal | 18 kelas kebijakan + OE/OE-L, umur & residu buku komersial SAK EP (kendaraan 10%), kelompok fiskal PMK 72/2023, umur fiskal bangunan 20/40 th, batas kapitalisasi Rp5 jt / Rp25 jt kelompok & renovasi, tarif pajak tangguhan 22% — **semua dapat diubah**, status draf → disetujui FC |
| Penyusutan dua buku | Kartu aset › Keuangan & Penyusutan | Garis lurus bulan penuh, bulan pelepasan tidak disusutkan, saldo awal migrasi disusutkan prospektif, override per aset, jadwal tahunan komersial vs fiskal, peringatan batas kapitalisasi. Hanya perhitungan; jurnal tetap di sistem akuntansi |
| Register dokumen aset | Aset Tetap & Dokumen › Register dokumen; tab Dokumen di kartu aset | Aset boleh punya 0, 1, atau banyak dokumen; satu dokumen (mis. polis) boleh menaungi banyak aset. 64 jenis dokumen, nomor register `LGL-IZN/2026/0001`, berkas berversi (SHA-256, versi lama tidak dihapus), klasifikasi umum/internal/terbatas/rahasia, perpanjangan, penggantian, pencabutan, log buka berkas |
| Pengingat & K3 | Lonceng notifikasi | H-90/60/30/7/0 (HGB H-3 th, SLF H-180, polis H-60, kontrak = batas pemberitahuan + 30 hari), eskalasi GM mulai H-30, riksa uji K3 kedaluwarsa → banner **Tidak boleh dioperasikan** di kartu aset (`assetcare:document-reminders`, harian 07:00) |
| M10 Keamanan | Pengaturan | 15 peran & 42 izin (deny by default), log audit dengan alasan & request id, ganti sandi wajib, batas login, MFA opsional, lampiran privat |

## Belum termasuk (Rilis B/C)

Suku cadang & stok, stock opname/inventarisasi berkala, disposal, anggaran, API `/api/v1`, mode offline penuh (antrian kiriman), integrasi PMS. Indikator yang bergantung pada modul ini tampil **N/A** dengan keterangan.

## Akun contoh

Super Admin: `admin@assetcare.local` / `AssetCare@GPCH2026`. Akun jabatan memakai `GPCH-2026!ganti` (wajib diganti):

| Email | Peran |
|---|---|
| gm@grandpasundanhotel.com | General Manager |
| engineering@grandpasundanhotel.com | Chief Engineering |
| eng.admin@grandpasundanhotel.com | Asset Controller |
| teknisi1@ / teknisi2@grandpasundanhotel.com | Teknisi |
| ca@grandpasundanhotel.com | Finance |
| fom@grandpasundanhotel.com | HOD + Front Office |
| fo.supervisor@ / hk.supervisor@grandpasundanhotel.com | Pelapor |
| owner@, yodi@, edp@, hk@, fbm@, purchasing@, wiwin@ | Owner, perwakilan owner, IT, HOD, Purchasing, Auditor |

Nama, email & batas persetujuan adalah **contoh** — konfirmasi dengan HR/manajemen sebelum dipakai. Data contoh juga berisi kamar 301–310 (Deluxe) dan template `TPL-DLX` (7 unit per kamar) untuk mencoba generate massal.

## Uji otomatis

`php -d memory_limit=1G vendor/bin/phpunit` (database `asset_care_test`) — 24 skenario. Pemeliharaan terencana (5): WO PM sekali saja H-7 & jadwal maju dari tanggal selesai, checklist PM wajib lengkap, PM berbasis jam operasi, WO predictive tunggal & validasi pembacaan, pembatalan tidak memajukan jadwal, halaman & laporan. Identitas perusahaan (1). Penyusutan, dokumen & peta aset (10): contoh kebijakan FFE-W Rp70 jt (komersial Rp10 jt, fiskal Rp17,5 jt, koreksi −Rp7,5 jt), residu kendaraan, bulan pelepasan, saldo awal prospektif, umur fiskal bangunan dapat diubah, status & deduplikasi pengingat, blokir K3, klasifikasi, alur unggah/perpanjang/ganti, render halaman & ekspor laporan. Operasional (8): AC17 (10 kamar → 70 kandidat), AC18 (kirim ulang tanpa duplikat), AC19 (konflik per kamar), AC20 (pencocokan aset lama), alur laporan → WO lengkap dengan kontrol, PDF label + QR + pemasangan + rotasi, AC27 mutasi, akses ditolak secara default.

## Catatan teknis

- Kebijakan aset tetap masih **draf**: ubah di Kelas aset kebijakan › *Batas kapitalisasi & umur fiskal*, lalu set status *Disetujui FC*. `assetcare:doctor` menandai bila belum disetujui.
- Penurunan nilai (impairment) mengurangi nilai buku komersial pada tahun dicatat; belum disebar ulang atas sisa umur.
- Dokumen wajib per kelas hanya laporan kekurangan, tidak memblokir penyimpanan aset.

- Waktu disimpan UTC, ditampilkan WIB (`ASSETCARE_DISPLAY_TIMEZONE`).
- Struktur sudah multi-properti (`property_id`), data awal satu hotel.
- URL label = `ASSETCARE_QR_BASE_URL/q/{token}`; tentukan domain final sebelum cetak massal.
- Pembaca QR memakai BarcodeDetector bawaan browser dengan cadangan jsQR (Apache-2.0, `public/js/vendor`).
