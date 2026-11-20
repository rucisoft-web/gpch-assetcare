#!/usr/bin/env bash
# Cadangan database Asset Care GPCH (gzip) ke ~/backups/asset-care-gpch, simpan 30 hari.
# Cron: 0 2 * * * /bin/bash /home/cpuser/asset-care-gpch/deploy/backup-db.sh >> /home/cpuser/backups/asset-care-backup.log 2>&1
set -euo pipefail
cd "$(dirname "$0")/.."
env_get() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB=$(env_get DB_DATABASE); USER=$(env_get DB_USERNAME); PASS=$(env_get DB_PASSWORD); HOST=$(env_get DB_HOST)
DEST="$HOME/backups/asset-care-gpch"
mkdir -p "$DEST"
FILE="$DEST/${DB}_$(date +%Y-%m-%d_%H%M).sql.gz"
MYSQL_PWD="$PASS" mysqldump -h "${HOST:-localhost}" -u "$USER" --single-transaction --quick --routines --no-tablespaces "$DB" | gzip > "$FILE"
# lampiran (foto, dokumen) ikut dicadangkan mingguan setiap Minggu
if [ "$(date +%u)" = "7" ]; then tar czf "$DEST/lampiran_$(date +%Y-%m-%d).tar.gz" -C storage/app private 2>/dev/null || true; fi
find "$DEST" -type f -mtime +30 -delete
echo "$(date '+%F %T') cadangan: $FILE ($(du -h "$FILE" | cut -f1))"
