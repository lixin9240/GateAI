#!/bin/bash
# ============================================================
# Hydropower AI System - Health Check
# Usage: bash health_check.sh
# Cron:  0 * * * * /opt/hydropower/scripts/health_check.sh
# ============================================================

set -e
APP_DIR="/opt/hydropower"
LOG_FILE="$APP_DIR/logs/health_check.log"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "=== Hydropower Health Check ==="

# ---- 1. Service Status ----
if systemctl is-active --quiet hydropower; then
    log "[OK] hydropower service running"
else
    log "[FAIL] hydropower service stopped - attempting restart..."
    sudo systemctl restart hydropower
    sleep 3
    if systemctl is-active --quiet hydropower; then
        log "[OK] service restarted successfully"
    else
        log "[CRITICAL] service restart failed!"
    fi
fi

# ---- 2. GPU ----
if command -v tegrastats &> /dev/null; then
    GPU_TEMP=$(cat /sys/devices/gpu.0/temp 2>/dev/null || echo "N/A")
    log "  GPU Temp: ${GPU_TEMP} C"
else
    log "  GPU: N/A (non-Jetson device)"
fi

# ---- 3. Disk ----
DISK_PCT=$(df -h "$APP_DIR" | tail -1 | awk '{print $5}' | tr -d '%')
if [ "$DISK_PCT" -gt 90 ]; then
    log "[WARN] Disk usage ${DISK_PCT}% - consider cleanup"
else
    log "[OK] Disk: ${DISK_PCT}%"
fi

# ---- 4. Memory ----
MEM_AVAIL=$(free -m | awk '/Mem:/{print $7}')
if [ "$MEM_AVAIL" -lt 500 ]; then
    log "[WARN] Memory low: ${MEM_AVAIL}MB available"
else
    log "[OK] Memory: ${MEM_AVAIL}MB available"
fi

# ---- 5. MySQL ----
if mysqladmin ping -u hydropower -pGYZ032411 --silent 2>/dev/null; then
    log "[OK] MySQL responding"
else
    log "[FAIL] MySQL not responding"
fi

# ---- 6. Model files ----
for f in dqn_scripted.pt lstm_state_dict.pt scaler_X.pkl; do
    if [ -f "$APP_DIR/models/$f" ]; then
        SIZE=$(ls -lh "$APP_DIR/models/$f" | awk '{print $5}')
        log "[OK] models/$f ($SIZE)"
    else
        log "[FAIL] models/$f MISSING!"
    fi
done

# ---- 7. Recent errors ----
if [ -f "$APP_DIR/logs/service_error.log" ]; then
    ERRORS=$(grep -c "Error\|ERROR\|Traceback" "$APP_DIR/logs/service_error.log" 2>/dev/null || echo 0)
    if [ "$ERRORS" -gt 0 ]; then
        log "[WARN] ${ERRORS} errors in last log cycle"
    fi
fi

# ---- 8. Network (PLC ping) ----
PLC_IP="192.168.1.10"
if ping -c 1 -W 2 "$PLC_IP" &>/dev/null; then
    log "[OK] PLC reachable ($PLC_IP)"
else
    log "[WARN] PLC unreachable ($PLC_IP)"
fi

log "=== Health Check Complete ==="
echo
