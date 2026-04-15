#!/bin/bash
set -e

BACKUP_DIR="/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILENAME="meridian_backup_${TIMESTAMP}.sql.gz"

echo "==> Starting backup at $(date)"

mysqldump -h "${DB_HOST:-db}" \
    -u "${DB_USERNAME:-meridian}" \
    -p"${DB_PASSWORD:-secret}" \
    --single-transaction \
    --routines \
    --triggers \
    "${DB_DATABASE:-meridian}" | gzip > "${BACKUP_DIR}/${FILENAME}"

echo "==> Backup saved: ${BACKUP_DIR}/${FILENAME}"

# Retention: delete backups older than 30 days
find "${BACKUP_DIR}" -name "meridian_backup_*.sql.gz" -mtime +30 -delete

echo "==> Cleaned up old backups (30-day retention)"
echo "==> Backup complete at $(date)"
