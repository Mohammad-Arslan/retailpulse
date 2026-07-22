# RetailPulse

Multi-branch retail & restaurant ERP — POS, inventory, procurement, accounting, HR/payroll, CRM/loyalty, and platform services — built as a **Laravel 13 + React/Inertia** modular monolith.

## Stack

| Layer | Technology |
| :--- | :--- |
| Backend | Laravel 13, PHP 8.3+, Eloquent, Spatie Permission |
| Frontend | React 18, Inertia.js 2, Tailwind CSS 4, Vite |
| Real-time | Laravel Reverb (WebSockets) |
| Data | MySQL 8, Redis 7 |
| Object storage | MinIO (S3-compatible) |
| Queues (local) | `queue:listen` / `queue:work` |
| Queues (production) | Laravel Horizon |
| HTTP (production) | Laravel Octane + FrankenPHP |
| Containers | Docker Compose project `retailpulse` |

## Quick start (Docker)

**Prerequisites:** Docker Desktop (Windows/macOS) or Docker Engine + Compose v2 (Linux), and Git Bash on Windows.

```bash
# Clone, then from the repo root:
cp .env.example .env   # if .env does not exist — setup.sh also does this

bash setup.sh              # Windows/Git Bash → local mode
# or
bash setup.sh production   # Linux server / production image (Octane + Horizon)
bash setup.sh local --rebuild   # force rebuild of the app image
```

One command builds/pulls images, resolves host port conflicts, starts the stack, runs **migrations + seeders**, and leaves the app serving.

### Default URLs (local)

| Service | URL |
| :--- | :--- |
| App | http://localhost:8000 |
| Vite HMR (local only) | http://localhost:5173 |
| Reverb | ws://localhost:8080 |
| Mailpit UI | http://localhost:8025 |
| MinIO Console | http://localhost:9001 |
| phpMyAdmin | http://localhost:8081 |
| MySQL (host) | `localhost:${MYSQL_HOST_PORT}` (default 3306) |
| Redis (host) | `localhost:${REDIS_HOST_PORT}` (default 6379) |

If a preferred port is already in use, `setup.sh` remaps it and writes the chosen value into `.env` (e.g. MySQL → `3307`).

### Containers & volumes

| Container | Role |
| :--- | :--- |
| `retailpulse-app` | Laravel + Reverb + queue/schedule (+ Vite locally / Octane+Horizon in production) |
| `retailpulse-mysql` | MySQL 8 |
| `retailpulse-redis` | Redis 7 |
| `retailpulse-mailpit` | Dev SMTP + inbox UI |
| `retailpulse-minio` | S3-compatible media storage |
| `retailpulse-phpmyadmin` | DB UI |
| `retailpulse-minio-init` | One-shot bucket bootstrap |

Named volumes: `retailpulse_mysql`, `retailpulse_redis`, `retailpulse_minio`, `retailpulse_vendor`, `retailpulse_node_modules`, `retailpulse_build`.

### Useful Compose commands

```bash
docker compose logs -f app
docker compose ps
docker compose down          # stop (keeps volumes)
docker compose down -v       # stop and delete volumes (destructive)
```

## Local without Docker (Laragon)

```bash
composer setup    # install PHP deps, .env, key, migrate, npm build
composer dev      # serve + queue + pail + vite + reverb
```

See [CLAUDE.md](CLAUDE.md) for the full command list.

Default super-admin (after seed) comes from `.env`:

```ini
SUPER_ADMIN_EMAIL=admin@retailpulse.local
SUPER_ADMIN_PASSWORD=...
```

## Documentation

| Doc | Purpose |
| :--- | :--- |
| [docs/README.md](docs/README.md) | Documentation index & reading order |
| [docs/vision.md](docs/vision.md) | Product vision |
| [docs/architecture/](docs/architecture/README.md) | Architecture Decision Records (authoritative) |
| [docs/srs.md](docs/srs.md) | Requirements |
| [docs/phases/](docs/phases/README.md) | Phase roadmap |
| [docs/implementation-status.md](docs/implementation-status.md) | What’s built today |
| [docs/deployment-guidelines.md](docs/deployment-guidelines.md) | **Deploy to Contabo VPS with Docker** |
| [CLAUDE.md](CLAUDE.md) / [AGENTS.md](AGENTS.md) | AI agent onboarding |

## Local AI (Ollama)

RetailPulse can call a **local** Ollama model via the Laravel AI SDK. The browser never talks to Ollama — only the backend does.

1. Install [Ollama](https://ollama.com/), then:

```bash
ollama pull qwen2.5-coder:7b
ollama run qwen2.5-coder:7b
```

2. In `.env`:

```ini
AI_PROVIDER=ollama
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=qwen2.5-coder:7b
```

3. Local-only test endpoint: `POST /api/dev/ai/ask` (404 unless `APP_ENV=local`).

## Security notes

- Never commit `.env` or real secrets.
- Production: use strong `APP_KEY`, DB, MinIO, and Reverb secrets; set `APP_DEBUG=false`.
- Prefer putting MySQL/Redis/MinIO behind a firewall and exposing only HTTP(S) via a reverse proxy (see deployment guidelines).

## License

Application code follows the project’s license terms. The upstream Laravel skeleton is MIT-licensed.
