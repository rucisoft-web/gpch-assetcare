@echo off
setlocal
rem ================================================================
rem  Asset Care GPCH - instalasi otomatis di Laragon (Windows)
rem  Jalankan dari Terminal Laragon:  cd C:\laragon\www\asset-care-gpch  lalu  setup-laragon.bat
rem  PERINGATAN: database asset_care_gpch akan dikosongkan & diisi ulang data awal.
rem ================================================================
cd /d "%~dp0"

echo [1/8] Memeriksa PHP...
php -r "exit(version_compare(PHP_VERSION,'8.3.0','>=')?0:1);"
if errorlevel 1 (
  echo PHP 8.3 atau lebih baru diperlukan. Laragon: Menu ^> PHP ^> Version.
  goto :gagal
)
php -r "foreach(['pdo_mysql','mbstring','intl','gd','zip','fileinfo','openssl'] as $e){if(!extension_loaded($e)){echo \"Ekstensi belum aktif: $e\n\";$x=1;}} exit($x??0);"
if errorlevel 1 (
  echo Aktifkan ekstensi di Laragon: Menu ^> PHP ^> Extensions, lalu ulangi.
  goto :gagal
)

echo [2/8] Menyiapkan file .env...
if not exist .env copy .env.example .env >nul

echo [3/8] Membuat APP_KEY...
php artisan key:generate --force
if errorlevel 1 goto :gagal

echo [4/8] Membuat database asset_care_gpch (utf8mb4)...
mysql -u root -e "CREATE DATABASE IF NOT EXISTS asset_care_gpch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
if errorlevel 1 (
  echo Gagal membuat database. Pastikan MySQL Laragon sudah Start All.
  goto :gagal
)

echo [5/8] Membuat tabel dan data awal...
php artisan migrate:fresh --seed --force
if errorlevel 1 goto :gagal

echo [6/8] Menyiapkan aset tampilan Filament...
php artisan filament:assets
php artisan storage:link >nul 2>&1

echo [7/8] Membersihkan cache...
php artisan optimize:clear

echo [8/8] Pemeriksaan akhir...
php artisan assetcare:doctor

echo.
echo ================================================================
echo  Selesai. Buka http://asset-care-gpch.test/admin
echo  Super Admin : admin@assetcare.local  /  AssetCare@GPCH2026
echo  Akun jabatan: kata sandi GPCH-2026!ganti (wajib diganti saat login)
echo ================================================================
goto :eof

:gagal
echo.
echo Instalasi berhenti. Baca pesan di atas atau PANDUAN-LARAGON.md bagian Masalah umum.
exit /b 1
