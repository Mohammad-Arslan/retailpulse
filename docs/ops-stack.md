# RetailPulse — Ops & Observability Stack

**Audience:** operators deploying Portainer, Jenkins, Uptime Kuma, and the Prometheus/Grafana/Loki stack alongside RetailPulse.  
**Related:** [deployment-guidelines.md](./deployment-guidelines.md), [docker-security-audit.md](./docker-security-audit.md), [ADR-018](./architecture/adr-018-deployment.md).

---

## 1. Design principles

| Principle | How we implement it |
| :--- | :--- |
| Do not disturb the app stack | Ops and observability use **separate Compose projects** (`retailpulse-ops`, `retailpulse-obs`), not services inside `docker-compose.yml` |
| Survive app redeploys | `bash setup.sh production --rebuild` runs `docker compose up --remove-orphans` on the **app** project only. Separate projects are never orphans of that run |
| Localhost only | Every ops UI port binds `127.0.0.1:` — public exposure is exclusively via host Nginx |
| Persistent data | Named volumes with explicit `name:` so they outlive container recreate |
| Same Docker network | Both projects attach to the existing external network `retailpulse` |

```
Internet
   │
   ▼
Host Nginx (TLS) ──► 127.0.0.1:8000   app (Octane)
                 ├──► 127.0.0.1:8080   Reverb
                 ├──► 127.0.0.1:9010   Portainer
                 ├──► 127.0.0.1:9080   Jenkins
                 ├──► 127.0.0.1:3001   Uptime Kuma
                 └──► 127.0.0.1:3000   Grafana
                          │
                 Docker network "retailpulse"
                          │
            mysql · redis · minio · prometheus · … 
```

---

## 2. Quick start

```bash
# 1) Core application (unchanged)
bash setup.sh production

# 2) Ops tooling
chmod +x scripts/ops-up.sh scripts/ops-down.sh
bash scripts/ops-up.sh

# 3) Observability (optional, more RAM)
bash scripts/ops-up.sh --with-observability
# or observability alone:
bash scripts/ops-up.sh --observability-only
```

Stop without touching the app:

```bash
bash scripts/ops-down.sh
bash scripts/ops-down.sh --with-observability
# destructive — deletes named volumes:
bash scripts/ops-down.sh --with-observability --volumes
```

---

## 2.1 Staging Contabo — zero-downtime rules

Observed live staging shape (do not break this):

| Item | Live value |
| :--- | :--- |
| Nginx | Host `nginx` on **:80 only**, `server_name` = public IPv4 |
| App proxy | `/` → `127.0.0.1:8000` (Octane) |
| Reverb | `/app/` → `127.0.0.1:8080` (WebSocket) |
| TLS | Not enabled yet (no domain) |
| Compose | Only `docker-compose.yml` project `retailpulse` |
| RAM | ~11 Gi total — leave headroom for MySQL/Octane |

**Safe order when rolling out this feature branch:**

1. Merge/deploy **application code only** via the usual path (`git pull` + `setup.sh` if needed). Do **not** run `docker compose down` on the app project.
2. Confirm `curl -sI http://127.0.0.1/up` still **200** and login still works.
3. Start ops with a **separate** project (never add these services into the app compose file):

   ```bash
   cd /var/www/retailpulse
   bash scripts/ops-up.sh
   ```

4. Access ops UIs via **SSH tunnels** (staging has no ops DNS names):

   ```bash
   ssh -L 9010:127.0.0.1:9010 \
       -L 9080:127.0.0.1:9080 \
       -L 3001:127.0.0.1:3001 \
       -L 3000:127.0.0.1:3000 \
       ubuntu@YOUR_VPS_IP
   ```

5. **Do not** replace `/etc/nginx/sites-available/retailpulse` with the multi-vhost HTTPS template until Certbot + real hostnames exist. Use `docker/nginx/retailpulse.staging.conf` if you need to refresh the site file — it matches the live IP/HTTP layout.

6. Enable `--with-observability` only if free RAM stays comfortable after Jenkins starts (Jenkins alone can use 1–2 Gi). Prefer ops-only first on this VPS size.

7. Planned localhost ports (9010, 9080, 3001, 3000, 9090, …) were verified **free** on staging before this doc; re-check with `ss -tlnp` before `ops-up` if anything else was installed.

### Local Docker Desktop (Windows/macOS)

```bash
bash setup.sh local          # core stack
bash scripts/ops-up.sh       # Portainer + Jenkins + Uptime Kuma
bash scripts/ops-up.sh --observability-only
```

Notes from local verification:

| Item | Behavior |
| :--- | :--- |
| `JENKINS_DOCKER_GID` | Auto-detected from docker.sock (often `0` on Docker Desktop) |
| `node-exporter` | Skipped unless `--linux-host-metrics` (needs Linux rootfs mount) |
| cAdvisor / Prometheus / Grafana / Loki | Work on Docker Desktop |
| App stack | Untouched — separate Compose projects |

**What will not crash the app if done correctly:** `scripts/ops-up.sh` only creates project `retailpulse-ops` / `retailpulse-obs`. It does not recreate `retailpulse-app` / MySQL / Redis / MinIO.

**What can cause downtime (avoid):**

- `docker compose down` / `-v` on the app project  
- Overwriting Nginx with SSL/`server_name` hostnames that don't exist  
- Running full observability + Jenkins on a memory-starved host without watching `free -h`  
- Binding ops ports to `0.0.0.0` (our compose binds `127.0.0.1` — keep it that way)
---

## 3. P1 — Portainer CE

**Compose:** `docker-compose.ops.yml` → service `portainer`  
**Image:** `portainer/portainer-ce:2.27.4`  
**Volume:** `retailpulse_portainer`  
**Ports:** `127.0.0.1:9010→9000` (HTTP), `127.0.0.1:9443→9443` (HTTPS)  
**Healthcheck:** `GET /api/status`  
**Restart:** `unless-stopped`

### Why not inside `docker-compose.yml`?

`setup.sh` uses `--remove-orphans`. Any service that exists as a container in project `retailpulse` but is missing from the Compose file used for `up` would be removed. A separate project avoids that class of outage.

### First login

1. Open `http://127.0.0.1:9010` (or `https://portainer.example.com` behind Nginx).
2. Create the admin user within **5 minutes** of first start (Portainer requirement).
3. Choose **Docker** environment → Unix socket (already mounted).

### Surviving app redeploys

Portainer data lives in `retailpulse_portainer`. Redeploying RetailPulse via `setup.sh` or GitHub Actions does **not** recreate or delete that volume.

### Docker socket

The socket is mounted read-write. Anyone with Portainer admin is effectively root on the host. Mitigations:

- Localhost bind + Nginx TLS + strong admin password  
- Optional IP allowlist in `docker/nginx/retailpulse.conf`  
- Prefer SSO / Portainer RBAC for teams  
- See [docker-security-audit.md](./docker-security-audit.md) for socket-proxy tradeoffs  

### Recommended Nginx

See `docker/nginx/retailpulse.conf` server block `portainer.example.com` (WebSocket upgrade required for the live console).

---

## 4. P2 — Jenkins

**Compose:** `docker-compose.ops.yml` → service `jenkins`  
**Image build:** `docker/jenkins/Dockerfile` (NOT the Laravel image)  
**Volume:** `retailpulse_jenkins`  
**Port:** `127.0.0.1:9080→8080` (avoids collision with Reverb `:8080`)  
**Agent port:** `127.0.0.1:50000→50000`  
**Healthcheck:** `GET /login`  
**Restart:** `unless-stopped`

### Image contents

| Tool | Purpose |
| :--- | :--- |
| Docker CLI + Compose plugin | Build `retailpulse-app` / run `docker compose` via host socket |
| Git | Checkout |
| Composer 2 | PHP dependencies |
| Node.js 20 + npm | Frontend build / tests |
| Suggested plugins | `docker/jenkins/plugins.txt` |

### First login — two paths

**Pre-secured (recommended):** set `JENKINS_ADMIN_USER` / `JENKINS_ADMIN_PASSWORD` in `.env` before the first `ops-up`. `docker/jenkins/security.groovy` (baked into the image, idempotent on every boot) then:

- creates that admin user via `HudsonPrivateSecurityRealm` (no self-signup),
- sets a `GlobalMatrixAuthorizationStrategy` granting **only** that admin `ADMINISTER` — anonymous gets nothing, matching this stack's "127.0.0.1 + SSH tunnel only" access model,
- turns on the CSRF crumb issuer explicitly,
- and marks the install state complete, so the manual setup wizard never shows.

Log in directly at `http://127.0.0.1:9080` with those credentials.

**Manual (default if the two env vars are blank):** the normal Jenkins setup wizard applies — itself secure by default (no anonymous access until unlocked with the file-based password below):

```bash
docker compose -p retailpulse-ops -f docker-compose.ops.yml logs jenkins | grep -i password
# or:
docker exec retailpulse-jenkins cat /var/jenkins_home/secrets/initialAdminPassword
```

Unlock Jenkins → install suggested plugins (also pre-seeded) → create admin by hand. There is intentionally no baked-in default admin password for either path.

### Docker group GID

Jenkins must share the host `docker` socket group. `scripts/ops-up.sh` auto-detects this (`stat -c '%g' /var/run/docker.sock`) whenever `JENKINS_DOCKER_GID` is unset in `.env` — set it explicitly only to override the detection. Do **not** leave a stale hardcoded value in `.env`: this repo's own Contabo staging box uses GID `988`, not the `999` shown in `.env.example` as a generic default — a hardcoded wrong value skips auto-detection and breaks Jenkins' access to the socket.

```bash
stat -c '%g' /var/run/docker.sock   # Linux — confirm the real value for this host
```

Then recreate: `bash scripts/ops-up.sh --rebuild-jenkins`.

### Pipeline — CI/CD source of truth

Committed at [`jenkins/Jenkinsfile`](../jenkins/Jenkinsfile):

1. Clone repository (full history — release notes need it, not a shallow clone)
2. Generate release notes (`git log <last-deployed-sha>..HEAD`)
3. `composer install --ignore-platform-reqs` (matches Dockerfile/CI)
4. `npm ci`
5. Pint (`./vendor/bin/pint --test`)
6. PHPUnit (`composer test`) — optional via parameter
7. `docker build --target production`
8. SSH → `bash setup.sh production --rebuild` (on by default — see below)
9. Optimize caches + `curl http://127.0.0.1:8000/up` health check, then record the deployed commit for the next run's release notes

**Auto-trigger:** the Jenkinsfile declares `triggers { pollSCM('H/2 * * * *') }` — Jenkins polls this job's configured SCM (`main`) every ~2 minutes and builds on new commits, with `DEPLOY_PRODUCTION` defaulting to `true`. No GitHub webhook is used: Jenkins stays `127.0.0.1`-only (per §1/§2.1) and only needs outbound internet, which it already has for `git`/`composer`/`npm`. This trades a small trigger latency for not exposing Jenkins to the internet on a box with no domain/TLS yet. Instant webhook triggering is possible later if a domain + TLS + a shared secret are set up, but it isn't done here — see [docker-security-audit.md](./docker-security-audit.md).

**Email notifications:** every build sends a success/failure email via `docker/jenkins/scripts/send-mail.py`, called once from a single `sendBuildNotification()` function in the Jenkinsfile (used by both the `success` and `failure` `post` blocks — no duplicated notification logic). On success, that day's release notes (commits since the last successful deploy) are attached as a text file. **SMTP transport is entirely env-driven** — `JENKINS_SMTP_HOST/PORT/USE_SSL/USE_TLS/USERNAME/PASSWORD`, `JENKINS_MAIL_FROM`, and the recipient list `JENKINS_NOTIFY_EMAIL`, all set on the `jenkins` service in `docker-compose.ops.yml` from `.env`. Defaults point at the local Mailpit catcher (it does not deliver real mail — dev/staging only). **Moving to a real SMTP provider is a `.env` change only**: set the real host/port/credentials, then `docker compose -p retailpulse-ops -f docker-compose.ops.yml up -d jenkins` to pick them up — the Jenkinsfile and `send-mail.py` never change.

**Credentials to create in Jenkins:**

| ID | Type | Use |
| :--- | :--- | :--- |
| `retailpulse-git` | Username/password or SSH | Checkout |
| `retailpulse-vps-ssh` | SSH private key | Deploy stage |

Create a Pipeline job → “Pipeline script from SCM” → point at `jenkins/Jenkinsfile` **once**, on `main`. That first build registers the `pollSCM` trigger; it's automatic from then on (same one-time-activation quirk GitHub Actions' `workflow_run` trigger has).

> **This flips the previous default.** Jenkins is now the CI/CD source of truth for deploys. `.github/workflows/deploy.yml` no longer auto-triggers (`workflow_dispatch` only) — it's a manual/break-glass fallback if Jenkins is down. `.github/workflows/ci.yml` still lints/tests every push and PR; it does not deploy. See [deployment-guidelines.md §9.1](./deployment-guidelines.md) and [ADR-018](./architecture/adr-018-deployment.md).

### Bootstrap scripts — wired up (2026-07-28)

`docker/jenkins/bootstrap-credentials.groovy` auto-creates the `retailpulse-vps-ssh` credential and the `retailpulse` Pipeline job on first boot against a **fresh** `retailpulse_jenkins` volume, then self-deletes. It's baked into the image at `/usr/share/jenkins/ref/init.groovy.d/99-retailpulse-bootstrap.groovy` — Jenkins' own entrypoint copies anything under `/usr/share/jenkins/ref/` into `$JENKINS_HOME` **once**, only if the destination doesn't already exist, which is what makes the self-delete safe (it deletes the writable volume copy, never this read-only image layer or a bind-mounted repo file).

It needs two inputs at `/var/jenkins_home/bootstrap/` (bind-mounted read-only from `docker/jenkins/bootstrap/`, per `docker-compose.ops.yml`):

- **`Jenkinsfile`** — auto-synced from `jenkins/Jenkinsfile` by `scripts/ops-up.sh` every time it runs. Never hand-edit the copy; edit the real file.
- **`retailpulse_staging_ed25519`** — the private key for VPS SSH access. **You** place this here manually before first boot (never committed — `docker/jenkins/bootstrap/.gitignore` blocks it). If it's missing, the bootstrap script logs an error and skips credential creation; Jenkins still starts. Add the key and `docker compose -p retailpulse-ops -f docker-compose.ops.yml restart jenkins` once it's there.

`docker/jenkins/refresh-job.groovy` is a **manual** companion tool, not baked into the image — use it to push a newer Jenkinsfile into an already-bootstrapped Jenkins without touching the credential:

```bash
docker cp docker/jenkins/refresh-job.groovy retailpulse-jenkins:/var/jenkins_home/init.groovy.d/99-refresh-retailpulse-job.groovy
docker restart retailpulse-jenkins
```

(In practice this is rarely needed — the Pipeline job re-reads `jenkins/Jenkinsfile` from SCM on every build anyway; `refresh-job.groovy` only matters if you changed how the job itself is defined, not the pipeline steps.)

---

## 5. P3 — Uptime Kuma

**Compose:** `docker-compose.ops.yml` → service `uptime-kuma`  
**Image:** `louislam/uptime-kuma:1.23.16`  
**Volume:** `retailpulse_uptime_kuma`  
**Port:** `127.0.0.1:3001→3001`  
**Healthcheck:** image `extra/healthcheck`  
**Restart:** `unless-stopped`

### Recommended monitors

| Name | Type | Target | Interval | Notes |
| :--- | :--- | :--- | :--- | :--- |
| RetailPulse Web | HTTP(s) | `https://erp.example.com/up` | 60s | Public path |
| Octane (local) | HTTP | `http://127.0.0.1:8000/up` | 30s | From Kuma container use `http://app:8000/up` |
| Reverb | TCP | `app:8080` | 60s | WebSocket server |
| Horizon | HTTP keyword / push | See below | 60s | No dedicated HTTP port — use Redis queue depth or a push heartbeat |
| MinIO | HTTP | `http://minio:9000/minio/health/live` | 60s | |
| MySQL | TCP | `mysql:3306` | 60s | |
| Redis | TCP | `redis:6379` | 60s | |
| Portainer | HTTP | `http://portainer:9000/api/status` | 120s | |
| Jenkins | HTTP | `http://jenkins:8080/login` | 120s | |

**Local / first-time seed:** after creating the Uptime Kuma admin user in the UI, run:

```bash
bash scripts/setup-uptime-kuma.sh
```

That loads the `RP *` monitors from `docker/uptime-kuma/seed-monitors.sql` (Docker DNS targets + `host.docker.internal` for the browser-facing app port), then restarts Kuma. Idempotent for names prefixed with `RP `.

It also creates the published status page **RetailPulse Local**:

- Dashboard: http://127.0.0.1:3001/dashboard  
- Status page: http://127.0.0.1:3001/status/retailpulse-local  
- Groups: Application · Data stores · Ops & observability  

**Horizon tip:** schedule a small Laravel command or external cron that POSTs to an Uptime Kuma **Push** monitor URL after `php artisan horizon:status` succeeds. Alternatively alert on Redis list length via Grafana (Prometheus) instead of HTTP.

### Alerting channels

Configure in Uptime Kuma → Settings → Notifications:

| Channel | When to use |
| :--- | :--- |
| Telegram / Discord | Fast on-call for small teams |
| Slack / Microsoft Teams | Org chat ops channel |
| Email (SMTP) | Audit trail; use production SMTP, not Mailpit |
| PagerDuty / Opsgenie | Formal on-call |
| Webhook | Bridge into existing incident tooling |

Production recommendations: page on **RetailPulse Web** and **MySQL/Redis** downs; warn-only on MinIO/Portainer/Jenkins; set retries ≥ 3 to avoid alert storms during deploys.

---

## 6. P4 — Prometheus + Grafana + Loki + Promtail

**Compose:** `docker-compose.observability.yml` (project `retailpulse-obs`)

| Service | Port (localhost) | Volume / config |
| :--- | :--- | :--- |
| Prometheus | 9090 | `retailpulse_prometheus`, `docker/prometheus/` |
| Grafana | 3000 | `retailpulse_grafana`, provisioning under `docker/grafana/` |
| Loki | 3100 | `retailpulse_loki`, `docker/loki/loki-config.yml` |
| Promtail | (internal 9080) | `docker/promtail/promtail-config.yml` |
| Blackbox exporter | 9115 | `docker/blackbox/blackbox.yml` |
| Redis exporter | 9121 | scrapes `redis:6379` |
| MySQL exporter | 9104 | DSN via `MYSQL_EXPORTER_DSN` |

### What Prometheus scrapes

| Job | Source | Meaning |
| :--- | :--- | :--- |
| `blackbox-http` | `/up`, MinIO health, Portainer, Jenkins, Kuma, Grafana | Application / ops HTTP availability |
| `blackbox-tcp` | app:8000, app:8080, mysql, redis, minio | Port liveness (Octane + Reverb) |
| `redis` / `mysql` | exporters | Datastore metrics |
| `minio` | `/minio/v2/metrics/cluster` | Object storage (requires `MINIO_PROMETHEUS_AUTH_TYPE`) |
| `cadvisor` | container metrics | Docker / Octane process resources |
| `node-exporter` | host metrics | CPU, RAM, disk, network |
| `prometheus` / `loki` / `grafana` | self | Stack health |

**Laravel application metrics:** RetailPulse does not yet ship a first-party `/metrics` Prometheus endpoint. Availability is covered by Blackbox → `/up`; resource usage by cAdvisor on `retailpulse-app`. A future authenticated metrics route (Phase 16) can be added without changing this scrape layout.

### Grafana

Default login from `.env`: `GRAFANA_ADMIN_USER` / `GRAFANA_ADMIN_PASSWORD` (change immediately).

Provisioned dashboards:

- **RetailPulse Overview** — probes, app CPU/RAM, Redis clients  
- **Host & Containers** — node-exporter + cAdvisor  
- **RetailPulse Logs** — Loki  

Community imports (optional): Grafana IDs **1860** (Node Exporter Full), **14282** (cAdvisor), **11835** (Redis), **7362** (MySQL).

### Loki / Promtail collection

| Source | How |
| :--- | :--- |
| Docker / Supervisor logs | Docker service discovery via socket; Octane/Horizon/Reverb log to stdout |
| Nginx | Bind-mount `${NGINX_LOG_PATH:-/var/log/nginx}` |
| Laravel `storage/logs` | Bind-mount `${LARAVEL_LOG_PATH:-./storage/logs}` |

On the VPS, if Laravel logs only exist inside the app container volume, either:

```bash
# Option A — stream via Docker logs (already collected), or
# Option B — bind-mount host path that mirrors storage/logs
LARAVEL_LOG_PATH=/var/www/retailpulse/storage/logs
```

---

## 7. P5 — Node Exporter + cAdvisor

| Metric class | Primary source | Example questions |
| :--- | :--- | :--- |
| CPU | node-exporter `node_cpu_seconds_total`; cAdvisor `container_cpu_usage_seconds_total` | Is the VPS saturated? Is `retailpulse-app` the culprit? |
| RAM | `node_memory_*`; `container_memory_working_set_bytes` | OOM risk before the kernel kills Octane |
| Filesystem | `node_filesystem_*` | MySQL/MinIO disk growth |
| Network | `node_network_*` | Bandwidth / iface errors |
| Docker / containers | cAdvisor | Per-container CPU, RSS, FS usage, network |

**cAdvisor** binds `127.0.0.1:9190` (not 8080 — reserved for Reverb).  
**Node Exporter** binds `127.0.0.1:9100`.

Both join network `retailpulse` and are scraped by Prometheus.

---

## 8. P6 — Nginx hardening

Reference config: [`docker/nginx/retailpulse.conf`](../docker/nginx/retailpulse.conf) + snippets.

| Area | Implementation |
| :--- | :--- |
| TLS | TLSv1.2/1.3, session tickets off, OCSP stapling |
| HTTP/2 | `http2 on;` |
| HTTP/3 | Commented QUIC listeners — enable only on nginx builds with `http_v3_module` |
| Security headers | HSTS, nosniff, frame options, Referrer-Policy, Permissions-Policy, CSP |
| CSP | Sensible default for Inertia/Vite/Echo; start with Report-Only if you need to inventory third parties |
| Rate limiting | `rp_general`, `rp_login`, `rp_ops` zones |
| Buffering | On for app; **off** for WebSockets (Reverb, Portainer, Kuma) |
| Compression | gzip for text/JS/CSS/SVG |
| Caching | Long-cache `/build/` (Vite hashed assets) |
| Proxies | App, Reverb `/app/`, Portainer, Jenkins, Uptime Kuma, Grafana |

**Octane safety:** keepalive upstream to `:8000`, `Connection ""` on the main location, generous `proxy_read_timeout`, no request buffering issues for normal POSTs (`proxy_request_buffering on` except Jenkins/WebSocket paths).

Install:

```bash
sudo mkdir -p /etc/nginx/snippets
sudo cp docker/nginx/snippets/*.conf /etc/nginx/snippets/
sudo cp docker/nginx/retailpulse.conf /etc/nginx/sites-available/retailpulse
# Edit hostnames + uncomment ssl_certificate lines after certbot
sudo ln -sf /etc/nginx/sites-available/retailpulse /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

If `limit_req_zone` duplicates another site file, move the zone definitions into `/etc/nginx/nginx.conf` inside `http {}` once.

---

## 9. Resource sizing

| Stack | Extra RAM (approx.) |
| :--- | :--- |
| Ops only (Portainer + Jenkins + Kuma) | 1–2 GB (Jenkins dominates) |
| + Observability | +1.5–3 GB |

Prefer Contabo Cloud VPS 20+ (16 GB) before enabling the full observability project on the same box as production MySQL.

---

## 10. Alignment with ADR-018

This stack implements the **Monitoring and alerting** expectations of [ADR-018](./architecture/adr-018-deployment.md) on a single VPS topology. Secrets still live in `.env` / GitHub Actions (Phase 16 secrets manager remains future work). Alertmanager can be added later; today Prometheus rules + Uptime Kuma notifications cover the critical path.

---

## 11. Deployment order & rollout checklist

Phased, not all-at-once — each phase should be confirmed stable before starting the next:

1. **Portainer + Jenkins + Uptime Kuma** (`bash scripts/ops-up.sh`) — fits comfortably in the ~8.5 GB available on the current 11 GB Contabo box.
2. **Let a real Jenkins build run** (composer + npm + `docker build` together spike well above Jenkins' idle footprint) and check `free -h` before deciding on step 3.
3. **Observability, optional** (`bash scripts/ops-up.sh --with-observability`) — only once step 2 shows comfortable headroom, or after upgrading the VPS. The script prints a RAM warning (not a block) if total system memory is under 16 GB when this flag is used.

### Pre-flight (do before the first `ops-up` on this VPS)

- [ ] `.env`: `JENKINS_DOCKER_GID` unset (let auto-detect find `988` on this box) or set to the confirmed real value — **not** the generic `999` default.
- [ ] `.env`: `JENKINS_ADMIN_USER` / `JENKINS_ADMIN_PASSWORD` set, so Jenkins boots pre-secured instead of via the manual wizard (§4).
- [ ] `.env`: `GRAFANA_ADMIN_USER` / `GRAFANA_ADMIN_PASSWORD` set to real values before ever using `--with-observability` (`ops-up.sh` warns if left at `changeme`, but does not block).
- [ ] `docker/jenkins/bootstrap/retailpulse_staging_ed25519` placed on the VPS (never committed).
- [ ] `.env`: `JENKINS_NOTIFY_EMAIL` set to a real recipient if you want build emails (Mailpit by default — doesn't deliver, only catches).
- [ ] A Contabo snapshot taken before first enabling any of this on the production box.

### Smoke test after `ops-up.sh`

- [ ] Portainer reaches the Docker socket — `http://127.0.0.1:9010` → environment shows the host's running containers (including the existing `retailpulse-*` app containers).
- [ ] Jenkins can run Docker — a manual build of the `retailpulse` job completes the "Build Docker image" stage without a permission error (confirms `JENKINS_DOCKER_GID` is correct).
- [ ] Jenkins can build and (if `DEPLOY_PRODUCTION` is on) deploy RetailPulse — job succeeds end to end, `/up` health check in the "Optimize & verify health" stage passes.
- [ ] The success email arrives (Mailpit UI at `http://127.0.0.1:8025` via tunnel, or the real inbox once SMTP is configured) with `release-notes.txt` attached.
- [ ] Uptime Kuma (`bash scripts/setup-uptime-kuma.sh`) reports all seeded `RP *` monitors up.
- [ ] **Existing app containers unaffected**: `docker compose -p retailpulse ps` still shows `retailpulse-app`, `mysql`, `redis`, `minio`, `mailpit`, `phpmyadmin` all healthy, none restarted, same `Up <duration>` as before `ops-up.sh` ran.
- [ ] `docker network inspect retailpulse` shows the ops/observability containers joined alongside the app containers — no new network created, no IP conflicts.
- [ ] `docker volume ls` shows only the new named `retailpulse_portainer` / `retailpulse_jenkins` / `retailpulse_uptime_kuma` (and, if observability is on, `retailpulse_prometheus` / `retailpulse_grafana` / `retailpulse_loki`) volumes added — nothing renamed or removed from the app stack's own volumes.
- [ ] No unexpected container restarts in `docker compose -p retailpulse ps` or `-p retailpulse-ops ps` over the following hour (`docker events` or Uptime Kuma's own uptime % is the easiest way to check after the fact).

## 12. Upgrade

Ops/observability images are pinned by tag (e.g. `grafana/grafana:11.5.2`, `portainer/portainer-ce:2.27.4`) — nothing auto-updates. To bump a version:

1. Edit the tag in `docker-compose.ops.yml` / `docker-compose.observability.yml`.
2. `docker compose -p retailpulse-ops -f docker-compose.ops.yml pull` (or the `-obs` equivalent).
3. `docker compose -p retailpulse-ops -f docker-compose.ops.yml up -d --remove-orphans` — recreates only the changed service(s); named volumes persist, the app stack is untouched (separate project).
4. Jenkins specifically: `bash scripts/ops-up.sh --rebuild-jenkins` after changing `docker/jenkins/Dockerfile` (new plugin, new PHP/Node version, etc.) — rebuilds the image, then recreates the container against the same `retailpulse_jenkins` volume (job/credential/build history survive).

## 13. Rollback

Because ops/observability are separate Compose projects, rolling back never touches the running app:

- **Roll back one service to a prior image tag:** edit the tag back, `docker compose ... up -d` — same as an upgrade, in reverse.
- **Stop everything ops-related, keep data:** `bash scripts/ops-down.sh [--with-observability]` — volumes untouched, safe to `ops-up.sh` again later.
- **Full removal including data (destructive):** `bash scripts/ops-down.sh --with-observability --volumes` — deletes Jenkins job history/credentials, Grafana dashboards state, Prometheus/Loki retained metrics/logs, Portainer config, Uptime Kuma monitor history. The app's own data (MySQL, Redis, MinIO) is in entirely separate named volumes and is never touched by this command.
- **Jenkins credential/job state specifically:** re-running `bash scripts/ops-up.sh` against a fresh `retailpulse_jenkins` volume re-bootstraps the admin security config and (if the deploy key is present at `docker/jenkins/bootstrap/`) the `retailpulse-vps-ssh` credential and `retailpulse` job automatically — no manual Jenkins UI work needed to recover.

## 14. Troubleshooting

| Symptom | Likely cause | Fix |
| :--- | :--- | :--- |
| Jenkins can't run `docker build` / `docker compose` steps (`permission denied` on the socket) | `JENKINS_DOCKER_GID` doesn't match this host's real docker group | `stat -c '%g' /var/run/docker.sock`, set `JENKINS_DOCKER_GID` to that value (or unset it and let `ops-up.sh` auto-detect), then `docker compose -p retailpulse-ops -f docker-compose.ops.yml up -d jenkins` |
| Jenkins boots straight to the manual setup wizard even though you set admin credentials | `.env` wasn't sourced before the container started, or the container predates the env var (old container still running with old env) | Confirm `docker exec retailpulse-jenkins printenv \| grep JENKINS_ADMIN`, then recreate: `docker compose -p retailpulse-ops -f docker-compose.ops.yml up -d --force-recreate jenkins` |
| `retailpulse-vps-ssh` credential never appears in Jenkins | `docker/jenkins/bootstrap/retailpulse_staging_ed25519` is missing | Add the key file, `docker compose -p retailpulse-ops -f docker-compose.ops.yml restart jenkins`, check `docker compose ... logs jenkins \| grep bootstrap` |
| Jenkins container crashes on boot citing `CASC_JENKINS_CONFIG` | That env var is set in `.env` but points at a missing/empty file | Unset `CASC_JENKINS_CONFIG` unless you have real YAML under `casc_configs` — it's optional, not required by anything in this stack |
| Grafana/Prometheus/Loki fail to start under `retailpulse-obs` | Started before the core app network exists | `docker network inspect retailpulse` must succeed first — start the core app (`bash setup.sh production`) before `ops-up.sh` |
| Ops containers get removed unexpectedly after an app redeploy | Something merged ops/observability services into `docker-compose.yml` itself | Don't — see §1; they must stay separate projects, or `setup.sh`'s `--remove-orphans` will remove them |
| Email notifications never arrive | `JENKINS_NOTIFY_EMAIL` blank, or Mailpit is the transport (it only catches, never delivers) | Set `JENKINS_NOTIFY_EMAIL`; check the Mailpit UI (`http://127.0.0.1:8025` via tunnel) to confirm the pipeline is actually sending; switch to real SMTP via `JENKINS_SMTP_*` when ready |
| `ops-up.sh` reports the wrong current memory / no warning shown | Running on Docker Desktop (macOS has no `free` command) | Expected — the script skips the live check there and says so; the warning only fires on Linux hosts where `free` exists |

## 15. Known limitations

- No coverage/load-test gate, no staging-vs-production two-tier deploy, no secrets manager — all explicitly Phase 16 scope; see [ADR-018](./architecture/adr-018-deployment.md)'s "Current implementation state."
- Jenkins auto-trigger is polling (`pollSCM`, ~2 min latency), not an instant webhook — deliberate, to avoid exposing Jenkins publicly on a box with no domain/TLS yet (§4).
- `docker/jenkins/refresh-job.groovy` is a manual companion tool, not wired into the image — see §4.
- Mailpit is the default mail transport for Jenkins notifications; it does not deliver real email until `JENKINS_SMTP_*` point at a real provider.
- Uptime Kuma has no dedicated Horizon monitor (no standalone HTTP port) — covered today by the Jenkins deploy health check and Redis queue depth via Prometheus if observability is enabled; a push-heartbeat monitor is the documented option if a dedicated check is wanted (§5).
- MySQL exporter (observability) authenticates as `root`; a scoped `exporter` user is recommended but not yet created (`docs/docker-security-audit.md` §2.5).
- Nginx templates document Uptime Kuma under `status.example.com`, not `kuma.example.com` — a deliberate naming choice (it's a public status page), not an oversight; rename in `docker/nginx/retailpulse.conf` if you'd rather match the tool name.

---

## Document history

| Date | Change |
| :--- | :--- |
| 2026-07-28 | Jenkins security bootstrap (`docker/jenkins/security.groovy`: matrix auth, no anonymous access, CSRF, skips the setup wizard once `JENKINS_ADMIN_USER`/`PASSWORD` are set — never a baked-in default). `scripts/ops-up.sh` now prints a resource estimate, warns (doesn't block) if observability is requested on <16 GB RAM, and warns on a default Grafana password. Added §11–§15 (rollout checklist, smoke test, upgrade, rollback, troubleshooting, known limitations). |
| 2026-07-28 | Jenkins promoted to CI/CD source of truth: `pollSCM` auto-trigger on `main` (no inbound webhook — Jenkins stays localhost-only), success/failure email via env-driven `docker/jenkins/scripts/send-mail.py` (Mailpit today, real SMTP later is a `.env`-only change), release notes attached on success. `.github/workflows/deploy.yml` demoted to manual/break-glass (`workflow_dispatch`) so it can't race Jenkins. Also fixed the previously-dead `bootstrap-credentials.groovy` wiring (§4) via Jenkins' `/usr/share/jenkins/ref/` first-boot seed convention + a new `docker/jenkins/bootstrap/` mount. |
| 2026-07-27 | Initial ops + observability integration (Portainer, Jenkins, Uptime Kuma, Prometheus, Grafana, Loki, Promtail, Node Exporter, cAdvisor, Nginx hardening) |
| 2026-07-27 | Staging Contabo zero-downtime rules (§2.1) + `docker/nginx/retailpulse.staging.conf` matching live IP/HTTP Nginx; SSH-tunnel access for ops UIs until DNS/TLS |
