#!/bin/bash
# ─── Server Resource Monitor ──────────────────────────────────────────────
# Run in a separate SSH session alongside the stress test.
# Usage: bash monitor.sh [tier-name]
#
# Samples CPU, RAM, PHP-FPM, queue jobs, DB connections every 5 seconds.
# Logs to results/monitor-{tier}-{timestamp}.log

TIER="${1:-default}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
LOGFILE="results/monitor-${TIER}-${TIMESTAMP}.log"
INTERVAL=5

mkdir -p results

# Auto-detect DB credentials from Laravel .env
if [ -f "../.env" ]; then
  DB_HOST=$(grep "^DB_HOST=" ../.env | cut -d= -f2)
  DB_PORT=$(grep "^DB_PORT=" ../.env | cut -d= -f2)
  DB_DATABASE=$(grep "^DB_DATABASE=" ../.env | cut -d= -f2)
  DB_USERNAME=$(grep "^DB_USERNAME=" ../.env | cut -d= -f2)
  DB_PASSWORD=$(grep "^DB_PASSWORD=" ../.env | cut -d= -f2)
fi

DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-tavlo}"
DB_USERNAME="${DB_USERNAME:-postgres}"

export PGPASSWORD="${DB_PASSWORD:-}"

echo "═══════════════════════════════════════════════" | tee "$LOGFILE"
echo "  Tavlo Server Monitor — Tier: $TIER"          | tee -a "$LOGFILE"
echo "  Started: $(date)"                             | tee -a "$LOGFILE"
echo "  Log: $LOGFILE"                                | tee -a "$LOGFILE"
echo "  Interval: ${INTERVAL}s"                       | tee -a "$LOGFILE"
echo "═══════════════════════════════════════════════" | tee -a "$LOGFILE"
echo ""                                               | tee -a "$LOGFILE"

sample_count=0

while true; do
  sample_count=$((sample_count + 1))
  TS=$(date '+%H:%M:%S')

  echo "─── Sample #${sample_count} at ${TS} ───" | tee -a "$LOGFILE"

  # CPU and Memory
  echo "[CPU/MEM]" | tee -a "$LOGFILE"
  # Use top for a snapshot
  if command -v top &>/dev/null; then
    if [[ "$(uname)" == "Darwin" ]]; then
      top -l 1 -n 0 2>/dev/null | head -10 | tee -a "$LOGFILE"
    else
      top -bn1 | head -5 | tee -a "$LOGFILE"
    fi
  fi

  # Memory details
  echo "[MEMORY]" | tee -a "$LOGFILE"
  if command -v free &>/dev/null; then
    free -m 2>/dev/null | tee -a "$LOGFILE"
  fi

  # Load average
  echo "[LOAD]" | tee -a "$LOGFILE"
  uptime | tee -a "$LOGFILE"

  # PHP-FPM processes
  echo "[PHP-FPM]" | tee -a "$LOGFILE"
  php_fpm_count=$(ps aux 2>/dev/null | grep -c "[p]hp-fpm" || echo "0")
  echo "  Active php-fpm processes: $php_fpm_count" | tee -a "$LOGFILE"

  # PHP-FPM status (if configured)
  if command -v curl &>/dev/null; then
    curl -s "http://localhost/fpm-status?json" 2>/dev/null | tee -a "$LOGFILE" || true
  fi

  # Nginx connections
  echo "[NGINX]" | tee -a "$LOGFILE"
  nginx_procs=$(ps aux 2>/dev/null | grep -c "[n]ginx" || echo "0")
  echo "  Nginx processes: $nginx_procs" | tee -a "$LOGFILE"
  curl -s "http://localhost/nginx_status" 2>/dev/null | tee -a "$LOGFILE" || true

  # Queue workers
  echo "[QUEUE WORKERS]" | tee -a "$LOGFILE"
  ps aux 2>/dev/null | grep "[q]ueue:work" | awk '{print "  PID:"$2, "CPU:"$3"%", "MEM:"$4"%", $11, $12, $13}' | tee -a "$LOGFILE"
  worker_count=$(ps aux 2>/dev/null | grep -c "[q]ueue:work" || echo "0")
  echo "  Total workers: $worker_count" | tee -a "$LOGFILE"

  # Queue job counts (database)
  echo "[QUEUE JOBS]" | tee -a "$LOGFILE"
  if command -v psql &>/dev/null; then
    psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -t -c \
      "SELECT queue, COUNT(*) as pending FROM jobs GROUP BY queue ORDER BY pending DESC;" \
      2>/dev/null | tee -a "$LOGFILE" || echo "  (DB query failed)" | tee -a "$LOGFILE"

    # Failed jobs in last 15 min
    echo "[FAILED JOBS (last 15 min)]" | tee -a "$LOGFILE"
    psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -t -c \
      "SELECT COUNT(*) as failed FROM failed_jobs WHERE failed_at > NOW() - INTERVAL '15 minutes';" \
      2>/dev/null | tee -a "$LOGFILE" || echo "  (DB query failed)" | tee -a "$LOGFILE"

    # Active DB connections
    echo "[DB CONNECTIONS]" | tee -a "$LOGFILE"
    psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -t -c \
      "SELECT state, COUNT(*) FROM pg_stat_activity WHERE datname = '${DB_DATABASE}' GROUP BY state;" \
      2>/dev/null | tee -a "$LOGFILE" || echo "  (DB query failed)" | tee -a "$LOGFILE"
  else
    echo "  psql not available" | tee -a "$LOGFILE"
  fi

  # Disk I/O (if available)
  echo "[DISK I/O]" | tee -a "$LOGFILE"
  if command -v iostat &>/dev/null; then
    iostat -x 1 1 2>/dev/null | tail -5 | tee -a "$LOGFILE"
  fi

  echo "" | tee -a "$LOGFILE"
  sleep "$INTERVAL"
done
