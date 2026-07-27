#!/usr/bin/env bash
# -----------------------------------------------------------------------------
# Seed RetailPulse monitors into Uptime Kuma (SQLite), then restart the container
# so Kuma reloads them into memory.
#
# Usage (from repo root, Git Bash / Linux):
#   bash scripts/setup-uptime-kuma.sh
#
# Requires: docker, retailpulse-uptime-kuma running, network retailpulse.
# Idempotent for names prefixed with "RP ".
# -----------------------------------------------------------------------------
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SQL_FILE="${ROOT_DIR}/docker/uptime-kuma/seed-monitors.sql"
STATUS_SQL_FILE="${ROOT_DIR}/docker/uptime-kuma/seed-status-page.sql"
VOLUME="${UPTIME_KUMA_VOLUME:-retailpulse_uptime_kuma}"
CONTAINER="${UPTIME_KUMA_CONTAINER:-retailpulse-uptime-kuma}"

if [[ ! -f "${SQL_FILE}" ]]; then
  echo "ERROR: missing ${SQL_FILE}" >&2
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "${CONTAINER}"; then
  echo "ERROR: ${CONTAINER} is not running. Start ops first: bash scripts/ops-up.sh" >&2
  exit 1
fi

echo "[kuma] Applying monitor seed to volume ${VOLUME}..."
docker run --rm \
  -v "${VOLUME}:/data" \
  -v "${SQL_FILE}:/seed.sql:ro" \
  alpine sh -c 'apk add --no-cache sqlite >/dev/null && sqlite3 /data/kuma.db < /seed.sql && sqlite3 -header -column /data/kuma.db "SELECT id, name, type FROM monitor ORDER BY id;"'

if [[ -f "${STATUS_SQL_FILE}" ]]; then
  echo "[kuma] Applying status page seed..."
  docker run --rm \
    -v "${VOLUME}:/data" \
    -v "${STATUS_SQL_FILE}:/seed.sql:ro" \
    alpine sh -c 'apk add --no-cache sqlite >/dev/null && sqlite3 /data/kuma.db < /seed.sql && sqlite3 -header -column /data/kuma.db "SELECT id, slug, title FROM status_page;"'
fi

echo "[kuma] Restarting ${CONTAINER} so monitors load..."
docker restart "${CONTAINER}" >/dev/null

echo "[kuma] Waiting for health..."
for _ in $(seq 1 30); do
  if curl -fsS "http://127.0.0.1:${UPTIME_KUMA_HOST_PORT:-3001}/" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

echo "[kuma] Done. Open http://127.0.0.1:${UPTIME_KUMA_HOST_PORT:-3001}/dashboard and refresh."
echo "[kuma] Status page: http://127.0.0.1:${UPTIME_KUMA_HOST_PORT:-3001}/status/retailpulse-local"
echo "[kuma] Note: Horizon has no public HTTP probe — use Redis monitor + Grafana, or add a Push monitor later."
