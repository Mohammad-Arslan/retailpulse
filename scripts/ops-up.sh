#!/usr/bin/env bash
# -----------------------------------------------------------------------------
# Start RetailPulse ops (+ optional observability) Compose projects.
# Does NOT touch the core app stack (docker-compose.yml / setup.sh).
#
# Usage:
#   bash scripts/ops-up.sh
#   bash scripts/ops-up.sh --with-observability
#   bash scripts/ops-up.sh --observability-only
#   bash scripts/ops-up.sh --observability-only --linux-host-metrics  # node-exporter (Linux VPS)
#   bash scripts/ops-up.sh --rebuild-jenkins
# -----------------------------------------------------------------------------
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

WITH_OPS=1
WITH_OBS=0
REBUILD_JENKINS=0
LINUX_HOST_METRICS=0

for arg in "$@"; do
  case "${arg}" in
    --with-observability) WITH_OBS=1 ;;
    --observability-only) WITH_OPS=0; WITH_OBS=1 ;;
    --rebuild-jenkins) REBUILD_JENKINS=1 ;;
    --linux-host-metrics) LINUX_HOST_METRICS=1 ;;
    -h|--help)
      sed -n '2,16p' "$0"
      exit 0
      ;;
    *)
      echo "ERROR: unknown argument: ${arg}" >&2
      exit 1
      ;;
  esac
done

if ! docker network inspect retailpulse >/dev/null 2>&1; then
  echo "ERROR: Docker network 'retailpulse' not found."
  echo "       Start the core stack first: bash setup.sh production"
  exit 1
fi

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env 2>/dev/null || true
  set +a
fi

# Docker Desktop (Windows/macOS) often exposes the socket as root:root (GID 0).
# Contabo/Ubuntu typically uses the docker group (999/998). Auto-detect when unset.
if [[ -z "${JENKINS_DOCKER_GID:-}" ]] && [[ -S /var/run/docker.sock ]]; then
  detected_gid="$(stat -c '%g' /var/run/docker.sock 2>/dev/null || stat -f '%g' /var/run/docker.sock 2>/dev/null || true)"
  if [[ -n "${detected_gid}" ]]; then
    export JENKINS_DOCKER_GID="${detected_gid}"
    echo "[ops] Detected docker.sock GID=${JENKINS_DOCKER_GID} → JENKINS_DOCKER_GID"
  fi
fi

# mysqld-exporter uses MYSQL_ROOT_PASSWORD from `.env` via Compose interpolation.
# Override the whole DSN only if you use a dedicated exporter user (URL-encode
# special characters in the password).
# MYSQL_EXPORTER_DSN=exporter:ENCODED_PASSWORD@(mysql:3306)/

if [[ "${WITH_OPS}" -eq 1 ]]; then
  echo "[ops] Starting Portainer + Jenkins + Uptime Kuma (project retailpulse-ops)..."
  if [[ "${REBUILD_JENKINS}" -eq 1 ]]; then
    docker compose -p retailpulse-ops -f docker-compose.ops.yml build --pull jenkins
  fi
  docker compose -p retailpulse-ops -f docker-compose.ops.yml up -d --remove-orphans
  echo "[ops] Portainer:  http://127.0.0.1:${PORTAINER_HOST_PORT:-9010}"
  echo "[ops] Jenkins:    http://127.0.0.1:${JENKINS_HOST_PORT:-9080}"
  echo "[ops] Uptime Kuma: http://127.0.0.1:${UPTIME_KUMA_HOST_PORT:-3001}"
  echo "[ops] Jenkins initial password (first boot):"
  echo "[ops]   docker exec retailpulse-jenkins cat /var/jenkins_home/secrets/initialAdminPassword"
fi

if [[ "${WITH_OBS}" -eq 1 ]]; then
  echo "[ops] Starting observability stack (project retailpulse-obs)..."
  OBS_ARGS=( -p retailpulse-obs -f docker-compose.observability.yml )
  if [[ "${LINUX_HOST_METRICS}" -eq 1 ]]; then
    OBS_ARGS+=( --profile linux-host )
    echo "[ops] Including node-exporter (--profile linux-host)"
  else
    echo "[ops] Skipping node-exporter (Docker Desktop incompatible). On Linux VPS add --linux-host-metrics"
  fi
  docker compose "${OBS_ARGS[@]}" up -d --remove-orphans
  echo "[ops] Grafana:     http://127.0.0.1:${GRAFANA_HOST_PORT:-3000}"
  echo "[ops] Prometheus:  http://127.0.0.1:${PROMETHEUS_HOST_PORT:-9090}"
  echo "[ops] Loki:        http://127.0.0.1:${LOKI_HOST_PORT:-3100}"
  echo "[ops] cAdvisor:    http://127.0.0.1:${CADVISOR_HOST_PORT:-9190}"
  if [[ "${LINUX_HOST_METRICS}" -eq 1 ]]; then
    echo "[ops] Node Exp:    http://127.0.0.1:${NODE_EXPORTER_HOST_PORT:-9100}"
  fi
fi

echo "[ops] Done. Named volumes persist across app redeploys (setup.sh)."
