#!/usr/bin/env bash
# ============================================================
#  Asset Care GPCH — instalasi / rilis di cPanel (Terminal / SSH)
#  Pemakaian:
#     bash deploy/deploy-cpanel.sh install   # pertama kali: tabel + data referensi + Super Admin (tanpa data contoh)
#     bash deploy/deploy-cpanel.sh seed      # pertama kali + data contoh (kamar 301–310, template Deluxe, akun jabatan)
#     bash deploy/deploy-cpanel.sh install-data  # pertama kali + DATA AWAL inventaris GPCH (aset, lokasi, foto)
#     bash deploy/deploy-cpanel.sh update    # rilis pembaruan kode (data tetap)
#  Variabel opsional: PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php
# ============================================================
set -euo pipefail
cd "$(dirname "$0")/.."
APP_DIR="$(pwd)"
MODE="${1:-update}"

# Cari PHP 8.3+ (CLI cPanel kadang default ke versi lama)
PHP_BIN="${PHP_BIN:-}"
if [ -z "$PHP_BIN" ]; then
  for c in /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /usr/local/bin/ea-php84 /usr/local/bin/ea-php83 php; do
    if command -v "$c" >/dev/null 2>&1 && "$c" -r 'exit(version_compare(PHP_VERSION,"8.3.0",">=")?0:1);'; then PHP_BIN="$c"; break; fi
  done
fi
[ -z "$PHP_BIN" ] && { echo "✘ PHP 8.3+ tidak ditemukan. Jalankan: PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php bash deploy/deploy-cpanel.sh $MODE"; exit 1; }
echo "» PHP: $PHP_BIN ($($PHP_BIN -r 'echo PHP_VERSION;'))"
ART="$PHP_BIN $APP_DIR/artisan"

missing=$($PHP_BIN -r 'foreach(["intl","pdo_mysql","mbstring","zip","gd","fileinfo","openssl","xml","ctype","tokenizer"] as $e) if(!extension_loaded($e)) echo "$e ";')
if [ -n "$missing" ]; then
  echo "✘ Ekstensi PHP belum aktif: $missing"
  echo "  Aktifkan di cPanel › Select PHP Version / MultiPHP INI Editor › Extensions (gd wajib untuk QR & PDF label)."
  exit 1
fi

[ -f vendor/autoload.php ] || { echo "» vendor tidak ada — composer install"; composer install --no-dev --optimize-autoloader --no-interaction; }
[ -f .env ] || { echo "✘ .env belum ada. Jalankan: cp deploy/.env.cpanel.example .env  lalu isi nilainya."; exit 1; }
grep -q '^DB_PASSWORD=.\+' .env || echo "⚠ DB_PASSWORD di .env masih kosong — pastikan memang benar."

echo "» Hak akses folder"
mkdir -p storage/app/private storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \; 2>/dev/null || true
find storage bootstrap/cache -type f -exec chmod 664 {} \; 2>/dev/null || true
chmod 600 .env

case "$MODE" in
  install|seed)
    $ART config:clear >/dev/null 2>&1 || true
    if $ART assetcare:installed >/dev/null 2>&1; then
      echo "✘ Database sudah berisi data. Instalasi dihentikan agar data tidak tertimpa. Untuk pembaruan pakai: bash deploy/deploy-cpanel.sh update"
      exit 1
    fi
    grep -q '^APP_KEY=base64' .env || $ART key:generate --force
    if [ "$MODE" = "seed" ]; then
      ASSETCARE_SEED_DEMO=true $ART migrate --seed --force
    else
      $ART migrate --seed --force
    fi
    ;;
  install-data)
    $ART config:clear >/dev/null 2>&1 || true
    if $ART assetcare:installed >/dev/null 2>&1; then
      echo "✘ Database sudah berisi data. Instalasi dihentikan agar data tidak tertimpa."
      exit 1
    fi
    SQL=database/sql/asset-care-gpch-data-awal.sql
    [ -f "$SQL" ] || { echo "✘ $SQL tidak ada. Pakai paket asset-care-gpch-lengkap.zip."; exit 1; }
    grep -q '^APP_KEY=base64' .env || $ART key:generate --force
    $ART config:clear >/dev/null 2>&1 || true
    echo "» Memuat data awal (struktur + referensi + 1.543 aset, 104 kelompok, 371 lokasi)"
    $ART assetcare:load-sql "$SQL"
    $ART migrate --force
    $ART db:seed --class=Database\\Seeders\\ReferenceSeeder --force
    echo "» Membuat label QR"
    $ART assetcare:labels-ensure
    TK=database/sql/data-awal-template-kamar.xlsx
    if [ -f "$TK" ]; then
      echo "» Template kamar & aset tetap per kamar"
      $ART assetcare:import-room-templates "$TK" --generate
    fi
    echo "» Kode aset berbasis lokasi & jenis (mis. MB-08-819-TV-01)"
    $ART assetcare:recode-assets
    $ART assetcare:complete-room-assets
    echo ""
    echo "  Login: admin@assetcare.local / AssetCare@GPCH2026 (wajib diganti saat login pertama)"
    ;;
  update)
    $ART down --refresh=15 || true
    $ART migrate --force
    $ART db:seed --class=Database\\Seeders\\ReferenceSeeder --force
    $ART assetcare:labels-ensure
    $ART assetcare:recode-assets
    $ART assetcare:complete-room-assets
    ;;
  *) echo "Mode tidak dikenal: $MODE (install | install-data | seed | update)"; exit 1 ;;
esac

$ART storage:link >/dev/null 2>&1 || true
$ART filament:assets
$ART optimize:clear
$ART optimize
$ART up || true
$ART assetcare:doctor
echo ""
echo "✔ Selesai ($MODE)."
echo "  Cron wajib (sekali per menit):  * * * * * $PHP_BIN $APP_DIR/artisan schedule:run >> /dev/null 2>&1"
