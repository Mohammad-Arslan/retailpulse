#!/usr/bin/env bash
set -euo pipefail

# -----------------------------------------------------------------------------
# RetailPulse one-shot Docker bootstrap
# Usage:
#   bash setup.sh                 # auto-detect (Windows Git Bash → local)
#   bash setup.sh local
#   bash setup.sh production
#   bash setup.sh local --rebuild # force image rebuild
# -----------------------------------------------------------------------------

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${ROOT_DIR}"

FORCE_REBUILD=0
MODE_ARG=""
for arg in "$@"; do
  case "${arg}" in
    --rebuild|-f) FORCE_REBUILD=1 ;;
    local|production) MODE_ARG="${arg}" ;;
    *)
      echo "ERROR: unknown argument: ${arg}"
      echo "Usage: bash setup.sh [local|production] [--rebuild]"
      exit 1
      ;;
  esac
done

resolve_mode() {
  local override="${1:-}"
  if [[ -n "${override}" ]]; then
    echo "${override}"
    return
  fi

  local uname_s
  uname_s="$(uname -s 2>/dev/null || echo unknown)"
  case "${uname_s}" in
    MINGW*|MSYS*|CYGWIN*)
      echo "local"
      return
      ;;
  esac

  if [[ -f .env ]]; then
    set -a
    # shellcheck disable=SC1091
    source .env 2>/dev/null || true
    set +a
  fi

  if [[ "${APP_ENV:-}" == "production" ]]; then
    echo "production"
  else
    echo "local"
  fi
}

MODE="$(resolve_mode "${MODE_ARG}")"
if [[ "${MODE}" != "local" && "${MODE}" != "production" ]]; then
  echo "ERROR: mode must be 'local' or 'production' (got: ${MODE})"
  exit 1
fi

if [[ ! -f .env ]]; then
  if [[ -f .env.example ]]; then
    echo "[setup] Copying .env.example → .env"
    cp .env.example .env
  else
    echo "ERROR: .env.example not found"
    exit 1
  fi
fi

# ---------------------------------------------------------------------------
# .env helpers
# ---------------------------------------------------------------------------
ensure_env_key() {
  local key="$1"
  local value="$2"
  if ! grep -qE "^${key}=" .env 2>/dev/null; then
    echo "${key}=${value}" >> .env
  elif grep -qE "^${key}=$" .env 2>/dev/null || grep -qE "^${key}=\"\"$" .env 2>/dev/null; then
    if command -v sed >/dev/null 2>&1; then
      sed -i.bak "s|^${key}=.*|${key}=${value}|" .env && rm -f .env.bak
    fi
  fi
}

set_env_key() {
  local key="$1"
  local value="$2"
  if grep -qE "^${key}=" .env 2>/dev/null; then
    sed -i.bak "s|^${key}=.*|${key}=${value}|" .env && rm -f .env.bak
  else
    echo "${key}=${value}" >> .env
  fi
}

get_env_key() {
  local key="$1"
  local default="${2:-}"
  local line
  line="$(grep -E "^${key}=" .env 2>/dev/null | tail -n1 || true)"
  if [[ -z "${line}" ]]; then
    echo "${default}"
    return
  fi
  echo "${line#*=}" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

# Docker MySQL image forbids MYSQL_USER=root — remap Laragon-style defaults.
if grep -qE '^DB_USERNAME=root$' .env 2>/dev/null; then
  sed -i.bak 's|^DB_USERNAME=root$|DB_USERNAME=retailpulse|' .env && rm -f .env.bak
fi
ensure_env_key "DB_PASSWORD" "secret"
ensure_env_key "MYSQL_ROOT_PASSWORD" "secret"
ensure_env_key "DB_DATABASE" "retailpulse"
ensure_env_key "DB_HOST" "127.0.0.1"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE=(docker-compose)
else
  echo "ERROR: neither 'docker compose' nor 'docker-compose' is available"
  exit 1
fi

# ---------------------------------------------------------------------------
# Port conflict detection / auto-remap
# ---------------------------------------------------------------------------
# Ports claimed during this setup run (prevents API+console both getting 9002).
RESERVED_PORTS=()

port_is_reserved() {
  local port="$1" r
  for r in "${RESERVED_PORTS[@]+"${RESERVED_PORTS[@]}"}"; do
    if [[ "${r}" == "${port}" ]]; then
      return 0
    fi
  done
  return 1
}

reserve_port() {
  RESERVED_PORTS+=("$1")
}

port_held_by_retailpulse() {
  local port="$1"
  # Include running and recently-created publish maps
  docker ps -a --filter "name=retailpulse-" --format '{{.Ports}}' 2>/dev/null \
    | grep -E "(^|[, ])(([0-9.]+)|\[::\]|:::)?[:.]?${port}->" >/dev/null 2>&1
}

port_listening_on_host() {
  local port="$1"

  # Bash /dev/tcp (Git Bash / Linux)
  if (echo >/dev/tcp/127.0.0.1/"${port}") >/dev/null 2>&1; then
    return 0
  fi

  # netstat (Windows / some Linux)
  if command -v netstat >/dev/null 2>&1; then
    if netstat -ano 2>/dev/null | grep -E "[:.]${port}[[:space:]]" | grep -i LISTEN >/dev/null 2>&1; then
      return 0
    fi
    if netstat -an 2>/dev/null | grep -E "[:.]${port}[[:space:]]" | grep -i LISTEN >/dev/null 2>&1; then
      return 0
    fi
  fi

  # ss (Linux)
  if command -v ss >/dev/null 2>&1; then
    if ss -ltn 2>/dev/null | grep -E "[:.]${port}[[:space:]]" >/dev/null 2>&1; then
      return 0
    fi
  fi

  # Any Docker container (including non-retailpulse) publishing the port
  if docker ps -a --format '{{.Names}} {{.Ports}}' 2>/dev/null \
    | grep -E "(:|::)${port}->" >/dev/null 2>&1; then
    # Our own stack publishing it is fine for reuse of that same mapping
    if port_held_by_retailpulse "${port}"; then
      return 1
    fi
    return 0
  fi

  return 1
}

port_is_available() {
  local port="$1"
  if port_is_reserved "${port}"; then
    return 1
  fi
  if port_listening_on_host "${port}"; then
    return 1
  fi
  return 0
}

find_free_port() {
  local preferred="$1"
  local max_offset="${2:-80}"
  local candidate offset
  for offset in $(seq 0 "${max_offset}"); do
    candidate=$((preferred + offset))
    if port_is_available "${candidate}"; then
      echo "${candidate}"
      return 0
    fi
  done
  echo "ERROR: could not find a free port near ${preferred}" >&2
  return 1
}

resolve_host_port() {
  local key="$1"
  local preferred="$2"
  local current chosen
  current="$(get_env_key "${key}" "${preferred}")"
  if [[ -z "${current}" ]]; then
    current="${preferred}"
  fi
  # Prefer existing .env value when free; otherwise preferred; else scan upward.
  if port_is_available "${current}"; then
    chosen="${current}"
  elif port_is_available "${preferred}"; then
    chosen="${preferred}"
  else
    chosen="$(find_free_port "${preferred}")"
  fi
  if [[ "${chosen}" != "${preferred}" ]] && [[ "${chosen}" != "${current}" ]]; then
    echo "[setup] Port conflict — ${key}: preferred ${preferred}, using ${chosen}" >&2
  elif [[ "${chosen}" != "${preferred}" ]]; then
    echo "[setup] Keeping remapped ${key}=${chosen} (preferred ${preferred} busy)" >&2
  fi
  reserve_port "${chosen}"
  set_env_key "${key}" "${chosen}"
  echo "${chosen}"
}

echo "[setup] Resolving host ports (auto-skip conflicts)..."
APP_HOST_PORT="$(resolve_host_port APP_HOST_PORT 8000)"
REVERB_HOST_PORT="$(resolve_host_port REVERB_HOST_PORT 8080)"
VITE_HOST_PORT="$(resolve_host_port VITE_HOST_PORT 5173)"
MYSQL_HOST_PORT="$(resolve_host_port MYSQL_HOST_PORT 3306)"
REDIS_HOST_PORT="$(resolve_host_port REDIS_HOST_PORT 6379)"
MAILPIT_SMTP_HOST_PORT="$(resolve_host_port MAILPIT_SMTP_HOST_PORT 1025)"
MAILPIT_UI_HOST_PORT="$(resolve_host_port MAILPIT_UI_HOST_PORT 8025)"
MINIO_API_HOST_PORT="$(resolve_host_port MINIO_API_HOST_PORT 9000)"
MINIO_CONSOLE_HOST_PORT="$(resolve_host_port MINIO_CONSOLE_HOST_PORT 9001)"
PHPMYADMIN_HOST_PORT="$(resolve_host_port PHPMYADMIN_HOST_PORT 8081)"

# Keep browser-facing settings aligned with published ports.
set_env_key "APP_URL" "http://localhost:${APP_HOST_PORT}"
set_env_key "REVERB_CLIENT_PORT" "${REVERB_HOST_PORT}"
set_env_key "VITE_HOST_PORT" "${VITE_HOST_PORT}"
# Keep DB_PORT as the in-container MySQL port (compose overrides host→mysql).
# Host tools use MYSQL_HOST_PORT for the published mapping.
set_env_key "DB_PORT" "3306"
set_env_key "REDIS_PORT" "${REDIS_HOST_PORT}"
set_env_key "MAIL_PORT" "${MAILPIT_SMTP_HOST_PORT}"
set_env_key "MINIO_ENDPOINT" "http://127.0.0.1:${MINIO_API_HOST_PORT}"
set_env_key "MINIO_URL" "http://localhost:${MINIO_API_HOST_PORT}/$(get_env_key MINIO_BUCKET retailpulse)"
set_env_key "AWS_ENDPOINT" "http://127.0.0.1:${MINIO_API_HOST_PORT}"
set_env_key "AWS_URL" "http://localhost:${MINIO_API_HOST_PORT}/$(get_env_key MINIO_BUCKET retailpulse)"

# Ensure Sanctum allows the remapped app + Vite origins.
SANCTUM_DOMAINS="$(get_env_key SANCTUM_STATEFUL_DOMAINS "localhost,127.0.0.1")"
for host_port in "localhost:${APP_HOST_PORT}" "localhost:${VITE_HOST_PORT}"; do
  if ! echo "${SANCTUM_DOMAINS}" | grep -q "${host_port}"; then
    SANCTUM_DOMAINS="${SANCTUM_DOMAINS},${host_port}"
  fi
done
set_env_key "SANCTUM_STATEFUL_DOMAINS" "${SANCTUM_DOMAINS}"

export BUILD_TARGET="${MODE}"
export APP_ENV="${MODE}"
export APP_HOST_PORT REVERB_HOST_PORT VITE_HOST_PORT MYSQL_HOST_PORT REDIS_HOST_PORT
export MAILPIT_SMTP_HOST_PORT MAILPIT_UI_HOST_PORT
export MINIO_API_HOST_PORT MINIO_CONSOLE_HOST_PORT PHPMYADMIN_HOST_PORT

# ---------------------------------------------------------------------------
# Images — skip pull/build when already present
# ---------------------------------------------------------------------------
image_exists() {
  docker image inspect "$1" >/dev/null 2>&1
}

container_exists() {
  docker ps -a --filter "name=^/${1}$" --format '{{.Names}}' 2>/dev/null | grep -qx "$1" \
    || docker ps -a --filter "name=^${1}$" --format '{{.Names}}' 2>/dev/null | grep -qx "$1"
}

container_running() {
  docker ps --filter "name=^/${1}$" --filter "status=running" --format '{{.Names}}' 2>/dev/null | grep -qx "$1" \
    || docker ps --filter "name=^${1}$" --filter "status=running" --format '{{.Names}}' 2>/dev/null | grep -qx "$1"
}

APP_IMAGE="retailpulse-app:${MODE}"
THIRD_PARTY_IMAGES=(
  "mysql:8.0"
  "redis:7-alpine"
  "axllent/mailpit:latest"
  "minio/minio:latest"
  "minio/mc:latest"
  "phpmyadmin:5"
)

echo "[setup] Mode: ${MODE}"

for img in "${THIRD_PARTY_IMAGES[@]}"; do
  if image_exists "${img}"; then
    echo "[setup] Image ${img} already present — skip pull"
  else
    echo "[setup] Pulling ${img}..."
    docker pull "${img}"
  fi
done

if [[ "${FORCE_REBUILD}" -eq 1 ]]; then
  echo "[setup] --rebuild: building ${APP_IMAGE}..."
  "${COMPOSE[@]}" build --pull
elif image_exists "${APP_IMAGE}"; then
  echo "[setup] Image ${APP_IMAGE} already present — skip build"
else
  echo "[setup] Building ${APP_IMAGE}..."
  "${COMPOSE[@]}" build
fi

# ---------------------------------------------------------------------------
# Containers — skip recreate when already running; otherwise up -d
# ---------------------------------------------------------------------------
CORE_CONTAINERS=(
  retailpulse-app
  retailpulse-mysql
  retailpulse-redis
  retailpulse-mailpit
  retailpulse-minio
)

all_core_running=1
for c in "${CORE_CONTAINERS[@]}"; do
  if ! container_running "${c}"; then
    all_core_running=0
    break
  fi
done

# minio-init is one-shot; treat successful exit as OK
minio_init_ok=0
if container_exists retailpulse-minio-init; then
  init_status="$(docker inspect -f '{{.State.Status}} {{.State.ExitCode}}' retailpulse-minio-init 2>/dev/null || echo 'missing 1')"
  if [[ "${init_status}" == "exited 0" ]] || [[ "${init_status}" == "running 0" ]]; then
    minio_init_ok=1
  fi
fi

if [[ "${all_core_running}" -eq 1 && "${minio_init_ok}" -eq 1 && "${FORCE_REBUILD}" -eq 0 ]]; then
  echo "[setup] Core containers already running — skip recreate"
  # Still refresh compose project in case env/ports changed (no-op if identical)
  "${COMPOSE[@]}" up -d --no-build --remove-orphans
else
  echo "[setup] Starting stack..."
  # --no-build: we already handled image build/skip above
  "${COMPOSE[@]}" up -d --no-build --remove-orphans
fi

echo ""
echo "════════════════════════════════════════════════════════════"
echo " RetailPulse is up (${MODE})"
echo "────────────────────────────────────────────────────────────"
echo " App:      http://localhost:${APP_HOST_PORT}"
if [[ "${MODE}" == "local" ]]; then
  echo " Vite:     http://localhost:${VITE_HOST_PORT}  (HMR — live frontend)"
fi
echo " Reverb:   ws://localhost:${REVERB_HOST_PORT}"
if [[ "${MODE}" == "production" ]]; then
  echo " Horizon:  http://localhost:${APP_HOST_PORT}/horizon"
fi
echo " Mailpit:  http://localhost:${MAILPIT_UI_HOST_PORT}  (SMTP :${MAILPIT_SMTP_HOST_PORT})"
echo " MinIO:    http://localhost:${MINIO_CONSOLE_HOST_PORT}  (API :${MINIO_API_HOST_PORT})"
echo " phpMyAdmin: http://localhost:${PHPMYADMIN_HOST_PORT}"
echo " MySQL:    localhost:${MYSQL_HOST_PORT}"
echo " Redis:    localhost:${REDIS_HOST_PORT}"
echo "════════════════════════════════════════════════════════════"
echo ""
echo " Logs:  ${COMPOSE[*]} logs -f app"
echo " Stop:  ${COMPOSE[*]} down"
echo " Tip:   bash setup.sh ${MODE} --rebuild   # force app image rebuild"
