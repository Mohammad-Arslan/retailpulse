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

### First login

```bash
docker compose -p retailpulse-ops -f docker-compose.ops.yml logs jenkins | grep -i password
# or:
docker exec retailpulse-jenkins cat /var/jenkins_home/secrets/initialAdminPassword
```

Unlock Jenkins → install suggested plugins (also pre-seeded) → create admin.

### Docker group GID

Jenkins must share the host `docker` socket group:

```bash
stat -c '%g' /var/run/docker.sock   # Linux
# set in .env:
JENKINS_DOCKER_GID=999
```

Then recreate: `bash scripts/ops-up.sh --rebuild-jenkins`.

### Sample pipeline

Committed at [`jenkins/Jenkinsfile`](../jenkins/Jenkinsfile):

1. Clone repository  
2. `composer install --ignore-platform-reqs` (matches Dockerfile/CI)  
3. `npm ci`  
4. Pint (`./vendor/bin/pint --test`)  
5. PHPUnit (`composer test`) — optional via parameter  
6. `docker build --target production`  
7. Optional: SSH → `bash setup.sh production --rebuild`  
8. Optimize caches + `curl http://127.0.0.1:8000/up` health check  

**Credentials to create in Jenkins:**

| ID | Type | Use |
| :--- | :--- | :--- |
| `retailpulse-git` | Username/password or SSH | Checkout |
| `retailpulse-vps-ssh` | SSH private key | Deploy stage |

Create a Pipeline job → “Pipeline script from SCM” → point at `jenkins/Jenkinsfile`, **or** paste/copy the file into a Multibranch Pipeline.

> GitHub Actions remains the default CI/CD path ([deployment-guidelines.md](./deployment-guidelines.md) §9.1). Jenkins is an optional on-VPS controller for environments that require it (air-gapped builds, Contabo-local orchestration, enterprise change windows).

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

## Document history

| Date | Change |
| :--- | :--- |
| 2026-07-27 | Initial ops + observability integration (Portainer, Jenkins, Uptime Kuma, Prometheus, Grafana, Loki, Promtail, Node Exporter, cAdvisor, Nginx hardening) |
| 2026-07-27 | Staging Contabo zero-downtime rules (§2.1) + `docker/nginx/retailpulse.staging.conf` matching live IP/HTTP Nginx; SSH-tunnel access for ops UIs until DNS/TLS |
