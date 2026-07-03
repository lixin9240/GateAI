#!/bin/bash
# ============================================================
# Hydropower AI System - MySQL Backup
# Usage: bash backup_db.sh
# Cron:  0 3 * * * /opt/hydropower/scripts/backup_db.sh
# ============================================================

BACKUP_DIR="/opt/hydropower/data/backups"
DB_USER="hydropower"
DB_PASS="GYZ032411"
DB_NAME="hydropower_smart"
RETENTION_DAYS=30

mkdir -p "$BACKUP_DIR"

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/hydropower_${DATE}.sql.gz"

mysqldump -u "$DB_USER" -p"$DB_PASS" --single-transaction --routines --triggers "$DB_NAME" \
    | gzip > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    SIZE=$(ls -lh "$BACKUP_FILE" | awk '{print $5}')
    echo "[$(date)] Backup OK: ${BACKUP_FILE} (${SIZE})"

    # Delete backups older than retention period
    find "$BACKUP_DIR" -name "hydropower_*.sql.gz" -mtime +$RETENTION_DAYS -delete
    echo "[$(date)] Cleaned backups older than ${RETENTION_DAYS} days"
else
    echo "[$(date)] Backup FAILED!" >&2
    exit 1
fi
