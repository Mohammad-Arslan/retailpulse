#!/usr/bin/env bash
# -----------------------------------------------------------------------------
# Stop ops / observability projects WITHOUT affecting the core app stack.
#
# Usage:
#   bash scripts/ops-down.sh
#   bash scripts/ops-down.sh --with-observability
#   bash scripts/ops-down.sh --observability-only
#   bash scripts/ops-down.sh --volumes   # also delete ops volumes (destructive)
# -----------------------------------------------------------------------------
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

WITH_OPS=1
WITH_OBS=0
DROP_VOLUMES=0

for arg in "$@"; do
  case "${arg}" in
    --with-observability) WITH_OBS=1 ;;
    --observability-only) WITH_OPS=0; WITH_OBS=1 ;;
    --volumes) DROP_VOLUMES=1 ;;
    -h|--help)
      sed -n '2,12p' "$0"
      exit 0
      ;;
    *)
      echo "ERROR: unknown argument: ${arg}" >&2
      exit 1
      ;;
  esac
done

DOWN_ARGS=(down)
if [[ "${DROP_VOLUMES}" -eq 1 ]]; then
  DOWN_ARGS+=( -v )
  echo "[ops] WARNING: volumes will be deleted"
fi

if [[ "${WITH_OPS}" -eq 1 ]]; then
  echo "[ops] Stopping retailpulse-ops..."
  docker compose -p retailpulse-ops -f docker-compose.ops.yml "${DOWN_ARGS[@]}" || true
fi

if [[ "${WITH_OBS}" -eq 1 ]]; then
  echo "[ops] Stopping retailpulse-obs..."
  docker compose -p retailpulse-obs -f docker-compose.observability.yml "${DOWN_ARGS[@]}" || true
fi

echo "[ops] Core app stack (retailpulse) was not touched."
