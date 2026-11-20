# Panduan Instalasi Asset Care GPCH di Laragon

Lokasi program: **`C:\laragon\www\asset-care-gpch`** · Alamat: **http://asset-care-gpch.test/admin**

Aplikasi ini terpisah dari Arah GPCH (`C:\laragon\www\arahgpch`) — database, folder, dan alamatnya berbeda, sehingga keduanya bisa berjalan bersamaan.

## 1. Prasyarat

| Komponen | Ketentuan | Cara di Laragon |
|---|---|---|
| PHP | **8.3 atau lebih baru** (64-bit, NTS) | Menu Laragon › PHP › Version (sama dengan yang dipakai Arah GPCH). |
| Ekstensi PHP | `intl`, `pdo_mysql`, `mbstring`, `zip`, `gd`, `fileinfo`, `openssl` | Menu › PHP › Extensions. **gd** wajib untuk QR & PDF label. |
| MySQL | MySQL 8.x (disarankan 8.4 LTS) | Bawaan Laragon. Klik **Start All**. User `root` tanpa kata sandi. |
| Composer / Node.js | Tidak dibutuhkan | Folder `vendor` sudah disertakan; tampilan memakai CSS statis. |

## 2. Salin program

1. Ekstrak `asset-care-gpch.zip` sehingga ada `C:\laragon\www\asset-care-gpch\artisan` (lewati bila folder sudah disalin langsung).
2. Klik **Reload** pada Laragon. Laragon membuat virtual host `asset-care-gpch.test` ke folder `public`.

## 3. Database & data awal

Buka **Terminal** Laragon:

```bat
cd C:\laragon\www\asset-care-gpch
setup-laragon.bat
```

Skrip: cek PHP & ekstensi → `.env` → `key:generate` → membuat database `asset_care_gpch` → `migrate:fresh --seed` → `filament:assets` → `optimize:clear` → `assetcare:doctor`.

Manual (bila perlu):

```bat
copy .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE asset_care_gpch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed
php artisan filament:assets
php artisan assetcare:doctor
```

> Menjalankan ulang `setup-laragon.bat` mengembalikan data awal — **semua data yang sudah diinput akan hilang**.

## 4. Masuk

| Akun | Email | Kata sandi |
|---|---|---|
| Super Admin (konsultan) | `admin@assetcare.local` | `AssetCare@GPCH2026` |
| Akun jabatan contoh (GM, Chief Engineer, teknisi, FO, HK, Finance, dll.) | lihat README.md | `GPCH-2026!ganti` → **wajib diganti saat login pertama** |

## 5. Scan QR dari ponsel

- Ponsel dan komputer Laragon harus satu jaringan. Ganti `APP_URL` di `.env` menjadi alamat yang bisa dibuka ponsel (mis. `http://192.168.1.20` dengan Apache Laragon, atau domain hotel), lalu `php artisan optimize:clear`.
- **URL ini tercetak permanen di label.** Tentukan domain produksi (mis. `https://asset.grandpasundanhotel.com`) di `ASSETCARE_QR_BASE_URL` **sebelum mencetak label massal**.
- Kamera langsung di browser hanya aktif pada **HTTPS** (atau localhost). Pada HTTP jaringan lokal gunakan tombol **Foto label** (ambil foto, QR dibaca otomatis) atau ketik kode aset. Aplikasi kamera bawaan ponsel juga bisa memindai label dan membuka link-nya.

## 6. Cetak label

Menu **Pengendalian Aset › Cetak label QR** → pilih kamar/lantai/batch → media (A4 50×30 mm 3×9, A4 75×50 mm 2×5, atau printer roll) → **Buat PDF label**. Cetak pada **skala 100% / Actual size** (matikan *Fit to page*). Pakai **PDF uji (garis bantu)** untuk mengecek posisi pada kertas label sebelum cetak massal.

## 7. Peringatan SLA (scheduler)

- Uji coba: Terminal Laragon → `php artisan schedule:work` (biarkan jendela terbuka).
- Permanen: Windows Task Scheduler, tiap 1 menit, program `C:\laragon\bin\php\php-8.3.x\php.exe`, argumen `C:\laragon\www\asset-care-gpch\artisan schedule:run`.
- Uji manual: `php artisan assetcare:sla-check`.

## 8. Perintah penting

| Perintah | Kegunaan |
|---|---|
| `php artisan assetcare:doctor` | Cek konfigurasi, database, izin & peran |
| `php artisan assetcare:create-admin` | Membuat / memulihkan Super Admin |
| `php artisan test` | Menjalankan uji penerimaan otomatis (butuh database `asset_care_test` kosong) |
| `php artisan optimize:clear` | Bersihkan cache setelah mengubah `.env` |

## 9. Masalah umum

| Gejala | Solusi |
|---|---|
| Halaman putih / error 500 | Lihat `storage\logs\laravel-*.log`; jalankan `php artisan assetcare:doctor`. |
| Tampilan berantakan | `php artisan filament:assets` lalu refresh (Ctrl+F5). |
| `could not find driver` | Aktifkan `pdo_mysql`. |
| PDF/QR gagal | Aktifkan ekstensi `gd`. |
| Login terus kembali ke halaman login | Pastikan `APP_URL` sama dengan alamat yang dibuka di browser. |
| Kamera tidak menyala | Normal di HTTP; gunakan **Foto label** atau pasang HTTPS. |
