# Panduan Instalasi Asset Care (GPCH) di cPanel

Panduan ini memasang **GPCH Asset Care** (Laravel 13 + Filament 5 + MySQL 8) di hosting cPanel, dengan pola yang sama seperti ArahGPCH:

- Folder aplikasi: `/home/cpuser/asset-care-gpch`, di **luar** `public_html`.
- Subdomain: `gpch-assetcare.rucisoft.id`, dengan document root `asset-care-gpch/public`.

Tampilan **sama dengan ArahGPCH**: warna hijau GPCH `#006632`, ornamen wayang, serta font Inter dan Cormorant. Berkas tema ada di `public/css/assetcare.css` dan `public/images/`, jadi tidak perlu pengaturan tambahan.

> Ganti `cpuser` dengan nama pengguna cPanel Anda, dan `rucisoft.id` dengan domain Anda.

---

## Ringkas: instalasi dengan data awal (paket lengkap)

Paket `asset-care-gpch-lengkap.zip` sudah berisi aplikasi, data awal (1.543 aset, 104 kelompok, 371 lokasi) dan 686 foto.

1. Buat subdomain `gpch-assetcare.rucisoft.id` dengan document root `asset-care-gpch/public` (langkah 1), set PHP 8.3 (langkah 2), buat database & user kosong (langkah 3).
2. File Manager → folder home → unggah `asset-care-gpch-lengkap.zip` → **Extract** (menghasilkan folder `asset-care-gpch`).
3. Terminal:
   ```bash
   cd ~/asset-care-gpch
   cp deploy/.env.cpanel.example .env
   nano .env        # isi DB_DATABASE, DB_USERNAME, DB_PASSWORD (dan MAIL_* bila perlu)
   bash deploy/deploy-cpanel.sh install-data
   ```
4. Pasang cron `schedule:run` (langkah 8), aktifkan HTTPS (langkah 7), lalu login `admin@assetcare.local` / `AssetCare@GPCH2026` dan ganti sandi.

Semua aset masuk sebagai **Draft · belum terverifikasi**, lengkap dengan label QR dan foto. Data yang diasumsikan tercatat di catatan tiap aset untuk dikoreksi saat identifikasi.

## Template kamar & aset tetap per kamar

Workbook `database/sql/data-awal-template-kamar.xlsx` (ikut di kedua paket zip) berisi 7 tipe kamar, 163 kamar Main Building lt 7–11 dan West Wing lt 1–3, template jenis aset tetap per tipe beserta jumlahnya, dan pengecualian per kamar. Sumbernya file inventaris kamar Housekeeping (MB) dan checklist inspeksi (WW), digabung dengan AC & TV yang sudah ada di data awal.

```bash
cd ~/asset-care-gpch
php artisan assetcare:import-room-templates database/sql/data-awal-template-kamar.xlsx --dry-run   # cek dulu
php artisan assetcare:import-room-templates database/sql/data-awal-template-kamar.xlsx --generate  # simpan + buat aset per kamar
```

- Tanpa `--generate`: hanya tipe kamar, template (published) dan pengecualian. Aset bisa dibuat belakangan lewat menu **Generate aset kamar**.
- Dengan `--generate`: AC & TV data awal dicocokkan ke slot kamarnya (tidak dobel), sisanya dibuat sebagai aset **draft** dengan label QR (±1.770 aset).
- Aman diulang. Bila workbook diedit lalu diimpor lagi, template yang berubah menjadi versi baru dan hanya kekurangan yang dibuat.
- `install-data` pada instalasi baru menjalankan langkah ini otomatis bila workbook ada.

## 0. Kebutuhan hosting

| Kebutuhan | Nilai |
|---|---|
| PHP | 8.3 atau 8.4 (MultiPHP) |
| Ekstensi PHP | pdo_mysql, mbstring, intl, gd, zip, fileinfo, openssl, xml, ctype, tokenizer |
| memory_limit | minimal 256M (untuk PDF label) |
| MySQL | 8.x (MariaDB 10.6+ umumnya juga jalan) |
| Fitur cPanel | Terminal (SSH), Cron Jobs, AutoSSL |
| Ruang disk | ±350 MB untuk aplikasi, ditambah lampiran foto |

Ekstensi diatur di **cPanel → Select PHP Version → Extensions**, atau di **MultiPHP INI Editor** untuk memory_limit.

---

## 1. Buat subdomain

1. Buka **cPanel → Domains → Create A New Domain**.
2. Isi Domain: `gpch-assetcare.rucisoft.id`.
3. **Hapus centang** "Share document root", lalu isi Document Root: `asset-care-gpch/public`.
4. Klik **Submit**.

Jika hosting tidak mengizinkan document root di luar `public_html`, lihat **Lampiran A**.

## 2. Atur versi PHP

Buka **cPanel → MultiPHP Manager**, centang `gpch-assetcare.rucisoft.id`, lalu pilih **PHP 8.3** (atau 8.4) dan klik **Apply**.

## 3. Buat basis data

Buka **cPanel → Manage My Databases**, atau gunakan **Database Wizard**:

1. Buat database `assetcare`. Nama lengkapnya menjadi `cpuser_assetcare`.
2. Buat user `asset` dengan sandi kuat. Nama lengkapnya menjadi `cpuser_asset`. **Catat sandinya.**
3. Tambahkan user ke database dengan **ALL PRIVILEGES**.

## 4. Unggah aplikasi

1. Buka **cPanel → File Manager** dan masuk ke folder home (`/home/cpuser`), **bukan** ke `public_html`.
2. **Upload** `asset-care-gpch-cpanel.zip`.
3. Klik kanan zip tersebut, pilih **Extract**, dan ekstrak ke `/home/cpuser`.
4. Pastikan hasilnya berupa folder `/home/cpuser/asset-care-gpch`, yang berisi `app`, `public`, `vendor`, `deploy`, dan seterusnya.
5. Hapus file zip setelah selesai diekstrak.

## 5. Siapkan file `.env`

Buka **cPanel → Terminal**, lalu jalankan:

```bash
cd ~/asset-care-gpch
cp deploy/.env.cpanel.example .env
nano .env
```

Ubah minimal nilai-nilai berikut:

| Kunci | Isi |
|---|---|
| `APP_URL` | `https://gpch-assetcare.rucisoft.id` |
| `ASSETCARE_QR_BASE_URL` | Sama dengan APP_URL. **Tercetak permanen di label**, lihat bagian 9. |
| `DB_DATABASE` | `cpuser_assetcare` |
| `DB_USERNAME` | `cpuser_asset` |
| `DB_PASSWORD` | Sandi dari langkah 3 |
| `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` | Akun email cPanel untuk notifikasi, misalnya `noreply@rucisoft.id` di port 465 |
| `ASSETCARE_ADMIN_PASSWORD` | Opsional. Jika kosong, sandi Super Admin dibuat acak. |
| `ASSETCARE_MFA_REQUIRED` | `true` (disarankan). Admin dan approver wajib memasang 2FA. |

Simpan dengan **Ctrl+O**, tekan **Enter**, lalu keluar dengan **Ctrl+X**. `APP_KEY` biarkan kosong karena akan diisi otomatis oleh skrip.

## 6. Instal aplikasi

Pilih **salah satu** cara berikut.

### Cara A (disarankan): skrip instalasi lewat Terminal

```bash
cd ~/asset-care-gpch
bash deploy/deploy-cpanel.sh install-data  # dengan DATA AWAL inventaris GPCH (paket asset-care-gpch-lengkap.zip)
# atau
bash deploy/deploy-cpanel.sh install     # basis data bersih: referensi + Super Admin
# atau
bash deploy/deploy-cpanel.sh seed        # sama, ditambah data contoh untuk uji coba/pelatihan
```

Skrip ini melakukan langkah-langkah berikut:

1. Memeriksa PHP dan ekstensi.
2. Mengatur hak akses folder.
3. Membuat APP_KEY.
4. Menjalankan migrasi dan seed.
5. Menjalankan `storage:link` dan mempublikasikan aset Filament.
6. Menyimpan cache konfigurasi.
7. Menjalankan pemeriksaan `assetcare:doctor`.

Di akhir proses akan muncul baris seperti ini:

```
Super Admin: admin@assetcare.local  /  AC-xxxxxxxxxxxxxx  (CATAT sekarang; wajib diganti saat login pertama)
```

**Catat sandi ini.** Sandi hanya ditampilkan sekali. Skrip juga menolak berjalan dua kali pada basis data yang sudah berisi data, supaya data tidak tertimpa.

### Cara B: impor SQL lewat phpMyAdmin (bila Terminal tidak tersedia)

1. Buka **phpMyAdmin**, pilih `cpuser_assetcare`, buka **Import**, lalu impor `database/sql/asset-care-gpch.sql` (kosong), **atau** `asset-care-gpch-data-awal.sql` dari folder `data-awal/` bila ingin langsung berisi data inventaris (lihat bagian *Data awal aset* di bawah).
2. Akun awal adalah `admin@assetcare.local` dengan sandi `AssetCare@GPCH2026`. Sandi ini wajib diganti saat login pertama.
3. Langkah berikutnya tetap memerlukan Terminal sekali (atau minta bantuan penyedia hosting):
   ```bash
   cd ~/asset-care-gpch && php artisan key:generate --force && bash deploy/deploy-cpanel.sh update
   ```

## 7. Aktifkan HTTPS

1. Buka `https://gpch-assetcare.rucisoft.id`. Jika ikon gembok muncul tanpa peringatan, SSL sudah terpasang otomatis. Lanjut ke langkah 2.
   Jika belum, pasang sertifikat gratis dengan salah satu cara berikut, sesuai menu yang tersedia di hosting Anda:
   - **cPanel → SSL/TLS Status**: centang subdomain, lalu klik **Run AutoSSL**.
   - **cPanel → Let's Encrypt SSL** (atau "Free SSL"): pilih subdomain, lalu klik **Issue/Install**.
   - Jika kedua menu itu tidak ada, minta CS hosting memasangkan SSL gratis untuk subdomain tersebut.

   Menu **SSL/TLS (Certificates)** hanya untuk mengunggah sertifikat manual, jadi tidak diperlukan.
2. Buka **cPanel → Domains**, lalu aktifkan **Force HTTPS Redirect** untuk `gpch-assetcare.rucisoft.id`.

HTTPS **wajib** karena dua alasan:

- Kamera untuk scan QR hanya berfungsi di HTTPS.
- Cookie sesi diatur sebagai *secure*.

## 8. Pasang Cron Jobs

Buka **cPanel → Cron Jobs** dan tambahkan dua baris berikut. Sesuaikan path PHP jika versinya berbeda. Path yang benar ditampilkan di akhir skrip instalasi.

| Jadwal | Perintah | Fungsi |
|---|---|---|
| Setiap menit (`* * * * *`) | `/opt/cpanel/ea-php83/root/usr/bin/php /home/cpuser/asset-care-gpch/artisan schedule:run >> /dev/null 2>&1` | Cek SLA, eskalasi, notifikasi, WO preventive otomatis (harian 06:00 WIB), dan pengingat masa berlaku dokumen aset (harian 07:00 WIB) |
| Harian 02:00 (`0 2 * * *`) | `/bin/bash /home/cpuser/asset-care-gpch/deploy/backup-db.sh >> /home/cpuser/backups/asset-care-backup.log 2>&1` | Backup database harian dan arsip lampiran mingguan, disimpan 30 hari di `~/backups/asset-care-gpch` |

## 9. Tentukan domain QR sebelum mencetak label

Setiap label QR berisi alamat `ASSETCARE_QR_BASE_URL/q/...`. **Setelah label dicetak dan ditempel, alamat ini tidak bisa diubah.** Karena itu:

- Tentukan domain final terlebih dahulu, misalnya `gpch-assetcare.rucisoft.id` atau domain resmi hotel.
- Jika domain diganti, ubah `ASSETCARE_QR_BASE_URL` di `.env` lalu jalankan `php artisan optimize`, **sebelum** mencetak massal.
- Cetak 1 lembar uji, lalu scan dengan HP untuk memastikan alamatnya membuka halaman aset yang benar.

## 10. Verifikasi

| Cek | Hasil yang diharapkan |
|---|---|
| Buka `https://gpch-assetcare.rucisoft.id` | Halaman login bertema ArahGPCH (hijau, wayang) |
| Buka `https://gpch-assetcare.rucisoft.id/.env` | **404**. Jika file .env terlihat, document root salah. Ulangi langkah 1. |
| `php artisan assetcare:doctor` di Terminal | Semua baris berstatus **OK**, kecuali "Kebijakan aset tetap disetujui FC" yang tetap PERIKSA sampai FC menyetujui draf kebijakan |
| Login sebagai Super Admin | Diminta mengganti sandi, lalu memasang 2FA (Google Authenticator) |
| Aset → pilih aset → **Cetak label PDF** | PDF A4 3×9 berisi QR, kode, nama, dan ruang |
| Menu **Scan QR** di HP | Kamera aktif dan label terbaca |

---

## Memperbarui ke rilis baru

1. Backup terlebih dahulu: `bash deploy/backup-db.sh`.
2. Unggah zip rilis baru, lalu ekstrak **menimpa** `~/asset-care-gpch`. File `.env` dan `storage/` tidak ikut tertimpa karena tidak ada di dalam paket.
3. Jalankan:
   ```bash
   cd ~/asset-care-gpch && bash deploy/deploy-cpanel.sh update
   ```
   Skrip ini akan memasang mode maintenance, menjalankan migrasi, memperbarui data referensi, membangun ulang cache, lalu menonaktifkan maintenance. Akun dan data tidak berubah.

### Pembaruan November 2026: kebijakan aset tetap & register dokumen

- `update` membuat tabel baru dan mengisi 20 kelas aset kebijakan, 7 kelompok harta fiskal, 64 jenis dokumen, dan daftar dokumen wajib per kelas. Kategori aset lama otomatis dipetakan ke kelas. Nilai yang sudah diubah FC tidak ditimpa.
- Tidak perlu cron baru: pengingat dokumen berjalan lewat `schedule:run` yang sudah ada.
- Berkas dokumen tersimpan privat di `storage/app/private/documents` dan ikut backup mingguan lampiran. Batas unggah 20 MB per berkas; bila PHP hosting lebih kecil, naikkan `upload_max_filesize` dan `post_max_size` di **cPanel → MultiPHP INI Editor**.
- Setelah update, buka **Aset Tetap & Dokumen → Kelas aset kebijakan → Batas kapitalisasi & umur fiskal** untuk mengisi keputusan FC.

### Pembaruan 16 November 2026: peta aset, PM & predictive, identitas perusahaan

- Nama aplikasi menjadi **Asset Care**. Identitas GPCH (logo, hijau Pasundan, ornamen wayang) otomatis terisi saat `update`; ubah kapan saja di **Pengaturan → Identitas perusahaan**. Logo tersimpan privat dan disajikan lewat `/brand/logo`, tidak perlu `storage:link`.
- Menu baru: **Aset → Peta aset**, **Pemeliharaan → Rencana PM / Pemantauan kondisi / Parameter kondisi & meter**.
- Langkah awal PM: buka **Rencana PM → Buat dari interval kategori** (memakai interval PM bawaan tiap kategori), lalu sunting checklist dan PIC per rencana.
- Tidak perlu cron baru; WO PM dibuat oleh `schedule:run` yang sudah terpasang.

### Pembaruan 18 November 2026 (2): label Tom & Jerry No.103 & handheld scanner

- Profil cetak baru **Tom & Jerry No.103 · 64 × 32 mm** (10 label per lembar, lembar ditempel di kertas A4). Atur posisi di Cetak label QR › Kalibrasi posisi label, lalu "Cetak lembar uji" di kertas HVS sebelum mencetak di lembar label.
- Menu Lapangan: sakelar **Pakai handheld scanner** di layar Pasang label & Scan. Handheld diatur ke mode keyboard dengan akhiran Enter.

### Pembaruan 18 November 2026: prosedur pasang label & kode aset berbasis lokasi

- Kode aset sekarang `LOKASI-JENIS-URUT`, mis. `MB-08-819-TV-01`. Perintah `bash deploy/deploy-cpanel.sh update` mengubah kode lama (`GPCH-AST-…`) secara otomatis; kode lama tetap bisa dicari. Bisa juga dijalankan manual: `php artisan assetcare:recode-assets --dry-run` (lihat contoh) lalu tanpa `--dry-run`.
- Alur label: Cetak label QR per lantai/kamar (ruang IT, peran baru **Staf Cetak Label (IT)**) → petugas membuka kamar di `/lapangan` → **Pasang label QR** → scan tiap label (label milik ruang lain ditolak) → isi kondisi & foto → Asset Controller menyetujui di **Pengendalian Aset › Persetujuan hasil lapangan**. Penyetuju harus berbeda dari petugas yang memeriksa.

### Pembaruan 17 November 2026: menu Lapangan untuk petugas identifikasi aset

- Peran baru **Petugas Identifikasi Aset** (`field-officer`). Petugas dengan peran ini login di `https://<domain>/lapangan` lewat ponsel dan hanya melihat menu Lapangan: Beranda (progres), Lokasi (gedung > lantai > kamar), Scan QR, Cari, dan Akun. Bila mereka membuka `/admin`, otomatis dialihkan ke `/lapangan`.
- Di setiap aset petugas menandai ditemukan / tidak ditemukan, memilih kondisi A-E, status operasional, merek/model/serial, mengambil foto, dan menulis catatan. Setelah simpan, aplikasi langsung membuka aset berikutnya di kamar yang sama.
- Aset yang ada di lapangan tetapi belum terdaftar dicatat lewat tombol "Aset temuan" (masuk sebagai draft, label QR diterbitkan).
- Pengguna dengan peran lain (admin, asset controller) tetap memakai panel web `/admin` seperti biasa, dan juga bisa membuka `/lapangan`.
- Setelah memperbarui kode, jalankan `bash deploy/deploy-cpanel.sh update` (atau `php artisan db:seed --class='Database\Seeders\ReferenceSeeder' --force`) agar peran baru terdaftar.

### Data awal aset (dari inventaris departemen)

Folder `data-awal/` (dikirim terpisah, tidak ada di repo publik) berisi:

| Berkas | Isi |
|---|---|
| `asset-care-gpch-data-awal.sql` | Struktur + data referensi + 1.543 aset draft, 104 kelompok jumlah, 371 lokasi, 686 tautan foto |
| `foto-storage-data-awal.zip` | Berkas foto, sudah dalam struktur folder aplikasi |
| `Data-awal-Asset-Care.xlsx` + `foto-data-awal.zip` | Versi yang bisa dikoreksi di Excel, lalu diimpor dengan perintah |

**Cara 1 — langsung ke MySQL (instalasi baru, database masih kosong):**

1. phpMyAdmin → pilih `cpuser_assetcare` → **Import** → `asset-care-gpch-data-awal.sql` (pengganti `asset-care-gpch.sql`, jangan impor keduanya).
2. File Manager → masuk ke `~/asset-care-gpch` → unggah `foto-storage-data-awal.zip` → **Extract** di folder itu (foto masuk ke `storage/app/private/assets/photos/awal`).
3. Terminal: `cd ~/asset-care-gpch && php artisan key:generate --force && bash deploy/deploy-cpanel.sh update`. Langkah ini juga membuat label QR untuk semua aset (token label dienkripsi dengan APP_KEY server, jadi tidak bisa ikut di SQL). Bila Terminal tidak ada, label tetap dibuat otomatis oleh cron `schedule:run` dalam 10 menit.

**Cara 2 — lewat workbook (bila ingin mengoreksi dulu di Excel, atau aplikasi sudah berisi data):**

1. Koreksi `Data-awal-Asset-Care.xlsx` (sheet **Petunjuk**; baris oranye = perlu dicek). Jangan ubah kolom **Kunci**.
2. Unggah workbook dan `foto-data-awal.zip` ke `~/asset-care-gpch/storage/app/import/`, ekstrak zip foto di sana.
3. Uji tanpa menyimpan, lalu jalankan sungguhan:
   ```bash
   cd ~/asset-care-gpch
   php artisan assetcare:import-initial storage/app/import/Data-awal-Asset-Care.xlsx --photos=storage/app/import/foto --dry-run
   php artisan assetcare:import-initial storage/app/import/Data-awal-Asset-Care.xlsx --photos=storage/app/import/foto --user=email-anda
   ```
4. Impor boleh diulang: aset yang sudah ada (berdasarkan Kunci) tidak digandakan dan tidak ditimpa. Hapus `storage/app/import/` setelah selesai.

Kedua cara menghasilkan aset berstatus **Draft · belum terverifikasi**, sehingga tim tetap memverifikasi fisik lewat scan label.

## Memulihkan dari backup

```bash
gunzip -c ~/backups/asset-care-gpch/cpuser_assetcare_YYYY-MM-DD_HHMM.sql.gz | mysql -u cpuser_asset -p cpuser_assetcare
tar -xzf ~/backups/asset-care-gpch/lampiran_YYYY-MM-DD.tar.gz -C ~/asset-care-gpch/storage/app
cd ~/asset-care-gpch && php artisan optimize:clear && php artisan optimize
```

> Simpan juga salinan file `.env` di tempat aman. **APP_KEY** dibutuhkan untuk membaca token label QR yang terenkripsi. Jika APP_KEY hilang, semua label harus dicetak ulang.

## Masalah umum

| Gejala | Penyebab dan solusi |
|---|---|
| Error 500 atau halaman putih | Lihat `storage/logs/laravel.log`. Biasanya disebabkan sandi DB salah atau `.env` belum lengkap. Setelah mengubah .env, jalankan `php artisan optimize`. |
| "Ekstensi ... tidak ada" saat menjalankan skrip | Aktifkan ekstensi di **Select PHP Version → Extensions** |
| Tampilan berantakan tanpa CSS | `APP_URL` tidak sama dengan alamat yang dibuka (http atau https). Perbaiki, lalu jalankan `php artisan optimize`. Jalankan juga `php artisan filament:assets`. |
| Foto atau lampiran tidak tampil | Jalankan `php artisan storage:link` |
| Login berulang kali kembali ke halaman login | Situs dibuka lewat http, padahal `SESSION_SECURE_COOKIE=true`. Aktifkan Force HTTPS. |
| Kamera scan tidak muncul | Situs belum HTTPS, atau izin kamera di browser ditolak |
| SLA atau notifikasi tidak berjalan | Cron `schedule:run` belum terpasang atau path PHP salah |
| PDF label gagal untuk banyak aset | Naikkan `memory_limit` ke 512M, atau cetak per ruang/kategori |
| Lupa sandi Super Admin | `php artisan assetcare:create-admin` untuk membuat admin baru |

## Lampiran A: jika document root harus di dalam `public_html`

**Opsi 1: symlink** (jika diizinkan hosting)

```bash
rm -rf ~/public_html/asset-gpch        # hanya jika folder ini kosong/bawaan subdomain
ln -s ~/asset-care-gpch/public ~/public_html/asset-gpch
```

Setelah itu, atur document root subdomain ke `public_html/asset-gpch`.

**Opsi 2: salin isi folder `public`**

1. Salin seluruh isi `~/asset-care-gpch/public/` ke `~/public_html/asset-gpch/`.
2. Edit `~/public_html/asset-gpch/index.php` dan ubah path-nya:
   ```php
   require __DIR__.'/../../asset-care-gpch/vendor/autoload.php';
   $app = require_once __DIR__.'/../../asset-care-gpch/bootstrap/app.php';
   ```
   Jika baris `maintenance.php` ada, ubah dengan pola yang sama ke `/../../asset-care-gpch/storage/framework/maintenance.php`.
3. Buat ulang tautan storage:
   ```bash
   ln -s ~/asset-care-gpch/storage/app/public ~/public_html/asset-gpch/storage
   ```
4. Setiap kali ada pembaruan, salin ulang `public/css`, `public/js`, dan `public/images`, lalu jalankan `php artisan filament:assets`.

## Catatan keamanan

- `APP_DEBUG=false` dan `APP_ENV=production`. Jangan ubah di server.
- `.env` sudah diatur dengan `chmod 600` oleh skrip. Jangan simpan file ini di `public_html`.
- `robots.txt` menolak semua mesin pencari.
- Admin dan approver wajib memakai 2FA (`ASSETCARE_MFA_REQUIRED=true`).
- Ganti sandi Super Admin awal. Buat akun per orang, dan jangan memakai akun bersama.
- Pantau `~/backups/asset-care-backup.log` secara berkala, dan unduh backup ke luar server minimal sebulan sekali.

## Ringkasan nilai

| Item | Nilai |
|---|---|
| Folder aplikasi | `/home/cpuser/asset-care-gpch` |
| Document root | `asset-care-gpch/public` |
| URL | `https://gpch-assetcare.rucisoft.id` |
| Database / user | `cpuser_assetcare` / `cpuser_asset` |
| Super Admin | `admin@assetcare.local`. Sandi ditampilkan saat instalasi (Cara A) atau `AssetCare@GPCH2026` (Cara B). |
| Cron | `schedule:run` setiap menit dan `backup-db.sh` setiap hari 02:00 |
| Backup | `~/backups/asset-care-gpch` (30 hari) |
