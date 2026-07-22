#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-local}"
APP_DIR="/var/www/html"
cd "${APP_DIR}"

log() {
  echo "[entrypoint] $*"
}

# ---------------------------------------------------------------------------
# Inside Docker, always prefer Compose service DNS over bind-mounted .env
# values like REDIS_HOST=127.0.0.1 (those are for host-side tooling only).
# Immutable Dotenv will not override these once exported/set by the runtime.
# ---------------------------------------------------------------------------
if [[ -f /.dockerenv ]]; then
  export DB_HOST=mysql
  export DB_PORT=3306
  export REDIS_HOST=redis
  export REDIS_PORT=6379
  export MAIL_HOST="${MAIL_HOST:-mailpit}"
  export MAIL_PORT="${MAIL_PORT:-1025}"
  export MINIO_ENDPOINT="${MINIO_ENDPOINT:-http://minio:9000}"
  log "Docker runtime — DB_HOST=mysql REDIS_HOST=redis"
fi

# ---------------------------------------------------------------------------
# Bootstrap .env from example if missing (bind mount may not have one yet)
# ---------------------------------------------------------------------------
if [[ ! -f .env ]]; then
  if [[ -f .env.example ]]; then
    log "No .env found — copying from .env.example"
    cp .env.example .env
  else
    log "ERROR: neither .env nor .env.example exists"
    exit 1
  fi
fi

# ---------------------------------------------------------------------------
# Safety nets for empty named volumes (first run overlays empty vendor/build)
# ---------------------------------------------------------------------------
if [[ ! -f vendor/autoload.php ]]; then
  log "vendor/ empty — running composer install"
  if [[ "${MODE}" == "production" ]]; then
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
  else
    composer install --no-interaction --prefer-dist --ignore-platform-reqs
  fi
fi

if [[ "${MODE}" == "local" ]]; then
  # Vite watcher needs node_modules; skip a full production build (HMR serves assets).
  if [[ ! -x node_modules/.bin/vite ]]; then
    log "node_modules incomplete — installing npm deps for Vite watcher"
    if [[ -f package-lock.json ]]; then
      npm ci
    else
      npm install
    fi
  fi
elif [[ ! -d public/build ]] || [[ -z "$(ls -A public/build 2>/dev/null || true)" ]]; then
  log "public/build empty — installing npm deps and building assets"
  if [[ -f package-lock.json ]]; then
    npm ci
  else
    npm install
  fi
  npm run build
fi

# ---------------------------------------------------------------------------
# Wait for MySQL (ping as root — more reliable during first boot)
# MariaDB client (default-mysql-client) enables SSL by default and fails against
# the stock MySQL image certs — always disable SSL for this health wait.
# ---------------------------------------------------------------------------
DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-${DB_PASSWORD:-}}"

log "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
ATTEMPTS=0
MAX_ATTEMPTS=60
until mysqladmin ping \
  -h"${DB_HOST}" \
  -P"${DB_PORT}" \
  -uroot \
  ${MYSQL_ROOT_PASSWORD:+-p"${MYSQL_ROOT_PASSWORD}"} \
  --skip-ssl \
  --silent 2>/dev/null; do
  ATTEMPTS=$((ATTEMPTS + 1))
  if [[ "${ATTEMPTS}" -ge "${MAX_ATTEMPTS}" ]]; then
    log "ERROR: MySQL not reachable after ${MAX_ATTEMPTS} attempts"
    mysqladmin ping \
      -h"${DB_HOST}" \
      -P"${DB_PORT}" \
      -uroot \
      ${MYSQL_ROOT_PASSWORD:+-p"${MYSQL_ROOT_PASSWORD}"} \
      --skip-ssl 2>&1 | sed 's/-p[^ ]*/-p***/g' || true
    exit 1
  fi
  sleep 2
done
log "MySQL is ready"

# ---------------------------------------------------------------------------
# Wait for Redis (session/cache/queue)
# ---------------------------------------------------------------------------
REDIS_HOST="${REDIS_HOST:-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"

log "Waiting for Redis at ${REDIS_HOST}:${REDIS_PORT}..."
ATTEMPTS=0
MAX_ATTEMPTS=60
until php -r '
  $host = getenv("REDIS_HOST") ?: "redis";
  $port = (int) (getenv("REDIS_PORT") ?: 6379);
  $r = new Redis();
  try {
    exit($r->connect($host, $port, 2.0) ? 0 : 1);
  } catch (Throwable $e) {
    exit(1);
  }
' 2>/dev/null; do
  ATTEMPTS=$((ATTEMPTS + 1))
  if [[ "${ATTEMPTS}" -ge "${MAX_ATTEMPTS}" ]]; then
    log "ERROR: Redis not reachable at ${REDIS_HOST}:${REDIS_PORT}"
    exit 1
  fi
  sleep 2
done
log "Redis is ready"

# ---------------------------------------------------------------------------
# Laravel bootstrap
# ---------------------------------------------------------------------------
if ! grep -qE '^APP_KEY=base64:.+' .env 2>/dev/null; then
  # Also handle empty APP_KEY=
  CURRENT_KEY="$(grep -E '^APP_KEY=' .env 2>/dev/null | cut -d= -f2- || true)"
  if [[ -z "${CURRENT_KEY}" ]] || [[ "${CURRENT_KEY}" == 'null' ]]; then
    log "Generating APP_KEY"
    php artisan key:generate --force
  fi
fi

php artisan storage:link --force 2>/dev/null || php artisan storage:link || true

log "Running migrations"
php artisan migrate --force

log "Running seeders"
php artisan db:seed --force

if [[ "${MODE}" == "production" ]]; then
  log "Caching config/routes/views/events (production)"
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache 2>/dev/null || true
  php artisan horizon:publish --no-interaction 2>/dev/null || true
else
  log "Clearing caches (local)"
  php artisan optimize:clear
fi

CONF="/etc/supervisor/retailpulse/supervisord.${MODE}.conf"
if [[ ! -f "${CONF}" ]]; then
  log "ERROR: supervisor config not found: ${CONF}"
  exit 1
fi

log "Starting supervisord (${MODE})"
exec supervisord -n -c "${CONF}"
