#!/usr/bin/env bash
# Instalasi GPCH Asset Care lewat cPanel › Terminal.
# Pakai:  cd ~/gpch-assetcare && bash install-cpanel.sh
# Bila PHP bawaan Terminal bukan 8.3+, tentukan sendiri:  PHP=/opt/cpanel/ea-php83/root/usr/bin/php bash install-cpanel.sh
set -euo pipefail
cd "$(dirname "$0")"

PHP="${PHP:-php}"
if ! "$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    for candidate in /opt/cpanel/ea-php85/root/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php \
                     /opt/alt/php85/usr/bin/php /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php; do
        if [ -x "$candidate" ]; then PHP="$candidate"; break; fi
    done
fi
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || { echo "PHP 8.3+ tidak ditemukan. Jalankan ulang dengan PHP=/path/ke/php83"; exit 1; }
echo "PHP: $("$PHP" -r 'echo PHP_VERSION;') ($PHP)"

for ext in pdo_mysql mbstring intl fileinfo openssl bcmath ctype tokenizer xml dom curl; do
    "$PHP" -m | grep -qi "^${ext}$" || echo "PERINGATAN: ekstensi PHP '$ext' belum aktif (cPanel › Select PHP Version › Extensions)."
done

if [ ! -f .env ]; then
    cp .env.example .env
    echo
    echo ".env dibuat dari .env.example. Isi dulu APP_URL, DB_DATABASE, DB_USERNAME, DB_PASSWORD,"
    echo "ASSET_ADMIN_EMAIL, ASSET_ADMIN_PASSWORD (dan ASSET_DEMO=true untuk memuat data GPCH), misalnya:"
    echo "    nano .env"
    echo "lalu jalankan lagi:  bash install-cpanel.sh"
    exit 0
fi

grep -q '^APP_KEY=base64:' .env || "$PHP" artisan key:generate --force
chmod -R ug+rwX storage bootstrap/cache

"$PHP" artisan migrate --seed --force

if grep -qi '^ASSET_DEMO=true' .env; then
    "$PHP" artisan asset:demo
fi

"$PHP" artisan optimize
"$PHP" artisan asset:doctor || true

echo
echo "Selesai. Arahkan document root domain ke: $(pwd)/public"
echo "Lalu kosongkan ASSET_ADMIN_PASSWORD di .env dan jalankan:  $PHP artisan optimize"
