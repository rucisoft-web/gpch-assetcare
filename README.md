# Ruci Asset Care

Aplikasi manajemen aset, intelijen aset eksekutif, dan layanan bagian umum (ruang rapat, kendaraan dinas, ATK & persediaan, dokumen perizinan) untuk hotel, rumah sakit, kampus, gedung kantor, atau pabrik. Satu instalasi melayani banyak perusahaan/properti dengan data yang terpisah.

Dibangun ulang dari modul Odoo 18 **GPCH Asset Care** (`gpch_asset_care` 18.0.1.3.0, untuk Grand Pasundan Convention Hotel) menjadi produk umum. Rumus skor, alur kerja, dan dasbor eksekutif dipertahankan; hal yang di Odoo khusus hotel (daftar departemen, label "Guest") dijadikan data per perusahaan. Data contoh GPCH disertakan.

## Teknologi

- PHP 8.3, Laravel 13, Filament 5 (Livewire). Tidak perlu Node.js di server.
- MySQL 8 / MariaDB 10.6+.
- Kode QR SVG dengan PHP murni (`chillerlan/php-qrcode`), tanpa GD/Imagick.
- Tidak ada antrean atau cron permanen; cocok untuk shared hosting cPanel.
- Foto disimpan privat di `storage/app/private`.

## Fitur

| Area | Isi |
|---|---|
| Dasbor Eksekutif | Asset Care Index, 8 KPI, kesehatan per departemen, distribusi kondisi, tren 12 bulan, kepatuhan PM, matriks risiko 5×5, biaya per kategori, daftar perhatian manajemen. **Semua bisa diklik** ke data di baliknya. |
| Register Aset | Skor kesehatan 0–100 (7 komponen berbobot), kondisi, kekritisan (5 dampak), skor risiko & prioritas otomatis, umur/sisa umur, tab Aset kritis / Perlu perhatian / Mendesak, pengelompokan per departemen/kategori/lokasi/kondisi |
| QR Asset ID | Token acak per aset, halaman HP publik (identitas, kesehatan, status) dengan formulir lapor kerusakan + foto, label A4 siap cetak (satuan atau massal), buat ulang QR |
| Identifikasi Awal | Survei aset awal dengan penugasan tim per lokasi, templat ruang/lokasi, bangkitkan dari templat, salin struktur lokasi, duplikasi aset, halaman Verifikasi Aset (ada / tidak ditemukan / aset tambahan) |
| Inspeksi | Hasil lulus/pantau/gagal, skor kondisi (bisa memperbarui kondisi fisik aset), meter, temuan, rekomendasi, foto |
| Permintaan Pemeliharaan | Baru → diverifikasi → work order dibuat / dibatalkan; laporan dari pindai QR ditandai |
| Work Order | Draf → ditugaskan → dikerjakan → menunggu suku cadang/vendor → selesai → diverifikasi → ditutup; biaya suku cadang/vendor/lain, jam kerja, downtime, akar masalah, foto |
| Pemeliharaan Preventif | Rencana mingguan s.d. tahunan, penanda terlambat, **Terbitkan WO** yang memajukan jatuh tempo otomatis (tambahan dibanding Odoo) |
| Register Risiko | Probabilitas × konsekuensi, tingkat otomatis, pemilik, target mitigasi |
| Usulan CAPEX | Kategori, rekomendasi, skor prioritas berbobot, status draf → diusulkan → disetujui/ditolak → terealisasi |
| Dasbor Bagian Umum | Rapat dan perjalanan dinas hari ini, persetujuan tertunda, kendaraan siap pakai, jatuh tempo dokumen & kendaraan (H-minus / lewat), stok menipis, nilai persediaan, BBM bulan ini, biaya servis YTD |
| Ruang Rapat | Daftar ruang (kapasitas, fasilitas), pemesanan dengan **cek bentrok jadwal** dan kapasitas, persetujuan manajer, jadwal mingguan per ruang |
| Kendaraan Dinas | Data kendaraan, pemesanan → disetujui (kendaraan & pengemudi ditetapkan, cek bentrok) → berangkat → kembali (odometer & jarak), log BBM, riwayat servis, pengingat pajak, STNK, KIR, asuransi, dan servis |
| ATK & Persediaan | Stok barang (ATK, cetakan, kebersihan, pantry) dengan batas minimum, stok masuk/keluar, stok opname, kartu mutasi, permintaan barang per departemen → disetujui → diserahkan (stok berkurang otomatis, bisa sebagian) |
| Dokumen & Perizinan | Izin, sertifikat (SLF, SILO), kontrak, polis, pajak; masa berlaku dengan jendela pengingat per dokumen, status berlaku/segera berakhir/kedaluwarsa, berkas PDF privat, tautan ke kendaraan atau aset |
| Jejak Audit | Siapa mengubah apa (nilai lama → baru untuk kolom penting), pengganti chatter Odoo |
| Admin Platform (`/platform`) | Perusahaan, akun, akses per perusahaan, reset MFA |

### Peran (per perusahaan)

| Peran | Setara Odoo | Hak |
|---|---|---|
| Manajer | Asset Care Manager | Semua, termasuk konfigurasi, CAPEX, persetujuan ruang/kendaraan/barang, hapus |
| Staf | Asset Care User | Catat dan ubah data operasional, ajukan pemesanan dan permintaan barang, serahkan barang; konfigurasi dan CAPEX hanya baca |
| Eksekutif | Asset Care Executive | Hanya baca (di Odoo eksekutif juga bisa menulis; di sini dibuat baca saja untuk pimpinan) |

## Rumus (sama dengan Odoo)

- **Kesehatan** = fisik 25% + kepatuhan pemeliharaan 20% + keandalan 15% + sisa umur 15% + keselamatan 10% + keandalan operasional 10% + dokumen 5%. Kondisi: ≥90 sangat baik, ≥75 baik, ≥60 cukup, ≥40 buruk, sisanya kritis.
- **Kekritisan** = rata-rata 5 dampak (1–5). ≥4,2 kritis, ≥3,3 tinggi, ≥2,2 sedang.
- **Risiko aset** = (100 − kesehatan) × kekritisan/5. ≥55 mendesak, ≥35 tinggi, ≥18 sedang.
- **Risiko register** = P × K. ≥20 kritis, ≥12 tinggi, ≥6 sedang.
- **Prioritas CAPEX** = (tamu 20% + keselamatan 30% + pendapatan 20% + urgensi 30%) × 20.
- **Asset Care Index** = kesehatan 30% + kepatuhan PM 20% + (100 − 10×risiko kritis) 20% + (100 − 5×WO darurat YTD) 10% + 80×10% + 100×10%. Dua komponen terakhir adalah konstanta di modul Odoo dan dipertahankan apa adanya.

## Menjalankan di komputer sendiri

```bash
composer install
cp .env.example .env            # ubah DB_*, APP_ENV=local, APP_DEBUG=true, APP_URL
php artisan key:generate
php artisan migrate --seed      # ASSET_DEMO=true dan ASSET_ADMIN_* untuk data contoh dan admin
php artisan serve
```

Buka `http://localhost:8000/app` (pengguna) atau `/platform` (admin platform). Data contoh ada di `/app/gpch`; akun dan kata sandi lihat [PANDUAN-CPANEL.md](PANDUAN-CPANEL.md#situs-demo--promosi).

Uji otomatis: `php artisan test` (SQLite) atau dengan MySQL: `DB_CONNECTION=mysql DB_DATABASE=... php artisan test`. 15 tes: rumus skor, isolasi antarperusahaan, alur permintaan → work order, status WO, PM, identifikasi awal, QR publik dan label, serta semua halaman untuk setiap peran.

## Pemasangan di cPanel

Lihat [PANDUAN-CPANEL.md](PANDUAN-CPANEL.md).

## Perintah artisan

| Perintah | Fungsi |
|---|---|
| `asset:create-admin email "Nama" --password=...` | Buat admin platform |
| `asset:demo` | Muat data contoh Grand Pasundan Convention Hotel |
| `asset:remove-demo` | Hapus data contoh |
| `asset:doctor` | Periksa kesiapan server (termasuk APP_URL untuk QR) |
| `asset:summary` | Ringkasan aset dan PM terlambat per perusahaan |

## Pemetaan dari Odoo

| Odoo (`gpch.*`) | Laravel |
|---|---|
| `asset.location` (complete_name rekursif) | `Location` (nama lengkap diperbarui berantai) |
| `asset.category` | `AssetCategory` (kode unik per perusahaan) |
| `asset` + selection `department` | `Asset` + tabel `departments` per perusahaan |
| `ir.sequence` AST/INS/MR/WO | `App\Support\Sequence` per perusahaan per tahun |
| `asset.template` / `template.line` | `AssetTemplate` / `AssetTemplateLine` |
| `asset.survey` / `survey.assignment` | `AssetSurvey` / `AssetSurveyAssignment` |
| Wizard generate / copy location / duplicate | `App\Services\AssetIntake` + aksi Filament |
| `asset.inspection`, `maintenance.request`, `maintenance.workorder`, `preventive.plan` | Model setara dengan transisi status yang dijaga |
| `asset.risk`, `asset.capex` | `AssetRisk`, `CapexProposal` |
| `asset.dashboard` + OWL | `App\Services\ExecutiveDashboard` + halaman Filament `DasborEksekutif` (agregasi SQL) |
| Controller QR + laporan PDF | `QrController` (halaman HP, formulir lapor, label cetak HTML/A4) |
| `mail.thread` tracking | `AuditLog` (trait `Auditable`) |
| Grup User/Manager/Executive | Peran per perusahaan (`Membership`) |
