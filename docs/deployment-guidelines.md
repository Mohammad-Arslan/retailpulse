# RetailPulse — Deployment Guidelines (Contabo VPS + Docker)

**Audience:** operators deploying RetailPulse to a Contabo VPS (or any Linux VPS with a public IPv4).  
**Runtime:** Docker Compose production target (`Octane` + `Horizon` + `Reverb`).  
**Related:** [ADR-018 Deployment](./architecture/adr-018-deployment.md), root [README.md](../README.md), [setup.sh](../setup.sh).

---

## 1. Overview

This guide takes you from a blank Contabo VPS to a running RetailPulse stack behind HTTPS.

| Component | How it runs on the VPS |
| :--- | :--- |
| App | `retailpulse-app` — Octane (FrankenPHP) :8000, Reverb :8080, Horizon, scheduler |
| Database | `retailpulse-mysql` — MySQL 8 (internal + optional host port) |
| Cache / queues / sessions | `retailpulse-redis` |
| Object storage (images) | `retailpulse-minio` |
| Mail (dev/staging) | `retailpulse-mailpit` — **replace with real SMTP in production** |
| DB UI (optional) | `retailpulse-phpmyadmin` — **do not expose publicly in production** |
| TLS / reverse proxy | Nginx or Caddy on the host (recommended) terminating HTTPS → app `:8000` |

One command boots the application stack:

```bash
bash setup.sh production
```

That builds the **production** image, starts Compose, waits for MySQL/Redis, runs **migrations + seeders**, then starts supervisord (Octane, Horizon, Reverb, schedule).

---

## 2. Contabo VPS sizing

| Workload | Suggested Contabo class | Notes |
| :--- | :--- | :--- |
| Staging / demo | Cloud VPS 10+ (2–4 vCPU, 8 GB RAM) | Comfortable for Docker + MySQL + Redis + MinIO |
| Small production (1–2 branches) | Cloud VPS 20+ (4+ vCPU, 16 GB RAM) | Prefer SSD; enable automatic backups |
| Growth | Scale vertically first; then split MySQL/Redis to dedicated instances | Aligns with ADR-018 shared-SaaS topology |

**OS:** Ubuntu 22.04 LTS or 24.04 LTS (x86_64).  
**Disk:** ≥ 40 GB SSD; plan growth for MySQL + MinIO media.

---

## 3. Contabo panel checklist

1. Create the VPS; note **public IPv4** and root password (or SSH key).
2. In Contabo firewall / security group (if enabled), allow at least:
   - **22** — SSH
   - **80** — HTTP (ACME / redirect)
   - **443** — HTTPS
3. Prefer **not** exposing MySQL (`3306`), Redis (`6379`), MinIO (`9000`/`9001`), Mailpit, or phpMyAdmin to `0.0.0.0`. Keep them on the Docker network; access via SSH tunnel when needed.
4. Point your domain DNS **A record** at the VPS IP (and optional `www` CNAME). Wait for DNS propagation before issuing certificates.

Example hostnames used in this guide:

- App: `https://erp.example.com`
- Reverb (WebSockets): `wss://erp.example.com` (proxied) **or** `wss://ws.example.com`
- MinIO (if public media URLs required): `https://media.example.com`

---

## 4. Server bootstrap (Ubuntu)

SSH in as root (or a sudo user):

```bash
ssh root@YOUR_VPS_IP
```

### 4.1 System updates & packages

```bash
apt update && apt upgrade -y
apt install -y ca-certificates curl gnupg git ufw fail2ban
```

### 4.2 Create a deploy user (recommended)

```bash
adduser deploy
usermod -aG sudo deploy
# Optional: copy your SSH public key to /home/deploy/.ssh/authorized_keys
```

Then disable password root login once key auth works (`/etc/ssh/sshd_config`).

### 4.3 Firewall (UFW)

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
# Only if you intentionally expose Reverb without a proxy:
# ufw allow 8080/tcp
ufw enable
ufw status
```

### 4.4 Install Docker Engine + Compose plugin

```bash
curl -fsSL https://get.docker.com | sh
usermod -aG docker deploy   # log out/in after this
docker --version
docker compose version
```

### 4.5 Optional: swap (small VPS)

```bash
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

---

## 5. Get the code onto the VPS

```bash
sudo mkdir -p /var/www
sudo chown deploy:deploy /var/www
su - deploy
cd /var/www
git clone YOUR_REPO_URL retailpulse
cd retailpulse
```

Private repos: use a deploy key or HTTPS token with least privilege.

---

## 6. Production `.env`

```bash
cp .env.example .env
nano .env   # or vim
```

### 6.1 Required production values

Generate a strong app key **inside** the app container after first boot, or on any PHP host:

```bash
# After stack is up:
docker compose exec app php artisan key:generate --force
```

Set at minimum:

```ini
APP_NAME=RetailPulse
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.example.com

SANCTUM_STATEFUL_DOMAINS=erp.example.com,www.erp.example.com

# Strong unique passwords — never reuse Laragon/dev defaults
DB_DATABASE=retailpulse
DB_USERNAME=retailpulse
DB_PASSWORD=CHANGE_ME_STRONG
MYSQL_ROOT_PASSWORD=CHANGE_ME_ROOT_STRONG

SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_EMAIL=admin@example.com
SUPER_ADMIN_PASSWORD=CHANGE_ME_STRONG

# Reverb — generate random strings
REVERB_APP_ID=retailpulse
REVERB_APP_KEY=CHANGE_ME_REVERB_KEY
REVERB_APP_SECRET=CHANGE_ME_REVERB_SECRET
REVERB_HOST=erp.example.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_CLIENT_HOST=erp.example.com
REVERB_CLIENT_PORT=443

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# MinIO
MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=CHANGE_ME_MINIO_STRONG
MINIO_ACCESS_KEY="${MINIO_ROOT_USER}"
MINIO_SECRET_KEY="${MINIO_ROOT_PASSWORD}"
MINIO_BUCKET=retailpulse
# Browser-reachable media URL (behind proxy) — see §8
MINIO_URL=https://media.example.com/retailpulse
AWS_ACCESS_KEY_ID="${MINIO_ROOT_USER}"
AWS_SECRET_ACCESS_KEY="${MINIO_ROOT_PASSWORD}"
AWS_BUCKET=retailpulse
AWS_URL=https://media.example.com/retailpulse
AWS_USE_PATH_STYLE_ENDPOINT=true
MEDIA_DISK=minio

# Real mail (production) — do not rely on Mailpit
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

OCTANE_SERVER=frankenphp
```

### 6.2 Host port variables

`setup.sh` may remap ports on conflict. For a clean VPS, defaults are fine:

```ini
APP_HOST_PORT=8000
REVERB_HOST_PORT=8080
MYSQL_HOST_PORT=3306
REDIS_HOST_PORT=6379
MINIO_API_HOST_PORT=9000
MINIO_CONSOLE_HOST_PORT=9001
PHPMYADMIN_HOST_PORT=8081
```

Inside containers, the entrypoint forces `DB_HOST=mysql` and `REDIS_HOST=redis` regardless of host-oriented `.env` values.

### 6.3 Production mail & phpMyAdmin

- Point `MAIL_*` at a real provider (SES, Postmark, Contabo SMTP, etc.).
- Prefer **removing** or firewalling `phpmyadmin` / `mailpit` services on public production. If you keep phpMyAdmin, bind it to localhost only and use an SSH tunnel:

```bash
ssh -L 8081:127.0.0.1:8081 deploy@YOUR_VPS_IP
# then open http://127.0.0.1:8081 on your laptop
```

---

## 7. First production boot

```bash
cd /var/www/retailpulse
bash setup.sh production
```

What this does:

1. Resolves free host ports and updates `.env` if needed  
2. Pulls third-party images if missing; builds `retailpulse-app:production` if missing  
3. Starts MySQL, Redis, MinIO, Mailpit, phpMyAdmin, app  
4. Entrypoint: wait MySQL/Redis → `migrate --force` → `db:seed --force` → Octane + Horizon + Reverb + schedule  

Verify:

```bash
docker compose ps
docker compose logs -f app
curl -I http://127.0.0.1:8000/up
```

Horizon UI (after reverse proxy): `https://erp.example.com/horizon`  
(Access controlled by `HorizonServiceProvider` — `admin.access` or `super-admin`.)

---

## 8. HTTPS reverse proxy (recommended)

Run **Nginx or Caddy on the host** (not inside the app container). Proxy to `127.0.0.1:8000` and WebSockets to `127.0.0.1:8080`.

### 8.1 TLS with Certbot + Nginx

```bash
sudo apt install -y nginx certbot python3-certbot-nginx
```

Example site `/etc/nginx/sites-available/retailpulse`:

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}

upstream retailpulse_app {
    server 127.0.0.1:8000;
    keepalive 32;
}

upstream retailpulse_reverb {
    server 127.0.0.1:8080;
}

server {
    listen 80;
    server_name erp.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name erp.example.com;

    # certbot will manage these lines:
    # ssl_certificate     /etc/letsencrypt/live/erp.example.com/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/erp.example.com/privkey.pem;

    client_max_body_size 64M;

    # WebSocket / Reverb (path used by Laravel Echo — adjust if you put Reverb on a subdomain)
    location /app/ {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_pass http://retailpulse_reverb;
        proxy_read_timeout 60s;
    }

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_pass http://retailpulse_app;
        proxy_read_timeout 120s;
    }
}
```

Enable and obtain certificates:

```bash
sudo ln -s /etc/nginx/sites-available/retailpulse /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d erp.example.com
```

### 8.2 MinIO public URL (media)

Either:

**A. Proxy API** (`media.example.com` → `127.0.0.1:9000`) and set `MINIO_URL` / `AWS_URL` to `https://media.example.com/retailpulse`, or  

**B.** Keep MinIO private and serve media through Laravel signed routes (future vault phase) — for now path-style public bucket download is enabled by `minio-init`.

After changing `.env` media URLs:

```bash
docker compose up -d app
docker compose exec app php artisan optimize:clear
# production:
docker compose exec app php artisan config:cache
```

### 8.3 Trust proxies

Ensure Laravel trusts the reverse proxy (`TrustProxies` / Laravel 13 defaults). With `X-Forwarded-Proto` set by Nginx, `URL::forceScheme('https')` is usually unnecessary if `APP_URL` is `https://…`.

---

## 9. Day-2 operations

### 9.1 Deploy a new release

```bash
cd /var/www/retailpulse
git pull origin main

# Rebuild app image when Dockerfile / PHP deps / frontend change
bash setup.sh production --rebuild

# Or, if only PHP/JS bind-mount matters and image is current:
docker compose up -d --no-build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

> Production entrypoint already runs migrate + seed on container start. For production go-lives with existing data, prefer **idempotent seeders** or temporarily skip seed by adjusting the entrypoint / using a dedicated release script once you harden Phase 16.

### 9.2 Logs

```bash
docker compose logs -f app
docker compose logs -f mysql
docker compose exec app php artisan horizon:status
```

### 9.3 Backups (minimum viable)

**MySQL** (nightly cron as `deploy`):

```bash
#!/usr/bin/env bash
set -euo pipefail
STAMP=$(date +%F_%H%M)
DIR=/var/backups/retailpulse
mkdir -p "$DIR"
docker compose -f /var/www/retailpulse/docker-compose.yml exec -T mysql \
  mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines retailpulse \
  | gzip > "$DIR/mysql_$STAMP.sql.gz"
# retain 14 days
find "$DIR" -name 'mysql_*.sql.gz' -mtime +14 -delete
```

Store `MYSQL_ROOT_PASSWORD` in a root-only env file, not in the script body.  
Copy dumps off-box (Contabo Object Storage, S3, rsync to another region) — aligns with ADR-018 RPO goals.

**MinIO / volumes:**

```bash
docker run --rm -v retailpulse_minio:/data -v /var/backups/retailpulse:/backup alpine \
  tar czf /backup/minio_$(date +%F).tar.gz -C /data .
```

### 9.4 Updates & security

```bash
sudo apt update && sudo apt upgrade -y
docker compose pull   # for mysql/redis/minio/phpmyadmin base images when desired
bash setup.sh production --rebuild
```

Enable Contabo snapshots / automatic backups in the panel as a second layer.

### 9.5 Restart / stop

```bash
docker compose restart app
docker compose down          # stop stack, keep data volumes
docker compose up -d
```

---

## 10. Security hardening checklist

| Item | Action |
| :--- | :--- |
| Secrets | Unique strong passwords; never commit `.env` |
| Debug | `APP_DEBUG=false`, `APP_ENV=production` |
| Firewall | Only 22/80/443 public |
| phpMyAdmin / Mailpit | Off or localhost + SSH tunnel |
| Horizon | Restricted to admins (already gated) |
| SSH | Key-only; Fail2ban; disable root password auth |
| TLS | Let’s Encrypt; HSTS optional via Nginx |
| Backups | Nightly DB + offsite copy; monthly restore test |
| Updates | Unattended security updates or weekly patch window |

---

## 11. Troubleshooting

| Symptom | Likely cause | Fix |
| :--- | :--- | :--- |
| `Bind for 0.0.0.0:PORT failed` | Host port conflict | Re-run `bash setup.sh production` (auto-remaps) or free the port |
| App up but Redis `Connection refused` | `.env` still pointing at `127.0.0.1` inside a process | Entrypoint/supervisor force `REDIS_HOST=redis`; restart app |
| MySQL wait fails with TLS/SSL | Client SSL to internal MySQL | Image uses `--skip-ssl` / client `ssl=0` — rebuild image if old |
| `502 Bad Gateway` from Nginx | App not listening on 8000 | `docker compose ps`, `logs -f app`, `curl 127.0.0.1:8000/up` |
| WebSockets fail | Reverb not proxied / wrong `REVERB_*` | Check `/app/` location, `REVERB_SCHEME=https`, browser uses `wss` |
| Horizon empty / jobs stuck | Redis down or wrong queue connection | Compose sets `QUEUE_CONNECTION=redis`; check `horizon:status` |
| Images 404 | MinIO URL not reachable from browser | Fix `MINIO_URL`/`AWS_URL` and proxy, or bucket policy |
| Seeder errors on redeploy | Non-idempotent seed data | Run migrate only; seed once on empty DB |

---

## 12. Architecture map (production)

```
Internet
   │
   ▼
Contabo VPS firewall (22, 80, 443)
   │
   ▼
Nginx / Caddy (TLS)
   ├─ https://erp.example.com  ──────► 127.0.0.1:8000  (Octane)
   └─ /app/ (WebSocket) ─────────────► 127.0.0.1:8080  (Reverb)
                                              │
                                    Docker network "retailpulse"
                          ┌───────────────────┼───────────────────┐
                          ▼                   ▼                   ▼
                       mysql:3306          redis:6379          minio:9000
```

---

## 13. Contabo-specific tips

1. **Snapshots** — take a Contabo snapshot before the first production cutover and before major upgrades.  
2. **Object Storage** — optional Contabo S3-compatible bucket for offsite MySQL dumps (instead of only local `/var/backups`).  
3. **DNS** — Contabo DNS or external DNS (Cloudflare); if using Cloudflare proxy (orange cloud), set SSL mode to **Full (strict)** and still terminate TLS on the VPS or at Cloudflare consistently.  
4. **IPv6** — if Contabo assigns IPv6, add AAAA records and listen on `[::]:443` in Nginx when ready.  
5. **Support** — keep Contabo VPS ID and panel 2FA enabled on your Contabo account.

---

## 14. Alignment with ADR-018

This runbook is the **practical Contabo path** for the Docker Compose topology called out in [ADR-018](./architecture/adr-018-deployment.md):

- Staging ≈ this stack with synthetic data and `APP_DEBUG` careful  
- Production ≈ same images + TLS + secrets + backups + no public admin sidecars  
- Phase 16 still owns CI/CD gates, secrets manager migration, and formal RTO/RPO drills — use this document as the interim operator guide until that pipeline exists

---

## Document history

| Date | Change |
| :--- | :--- |
| 2026-07-23 | Initial Contabo VPS + Docker production deployment guidelines |
| 2026-07-23 | Fixed `setup.sh` gap: `APP_URL` was being unconditionally reset to `http://localhost:<APP_HOST_PORT>` on every run (including `production`), overwriting the real domain/scheme set per §6.1. Now only auto-set in `local` mode. |
| 2026-07-23 | Fixed `docker-compose.yml` gap: the `app` service's `environment:` block hardcoded `APP_URL`, `REVERB_CLIENT_HOST`, `MINIO_URL`, `AWS_URL` to `localhost`-based values with no `.env` override path (`environment:` always wins over `env_file:`). `REVERB_CLIENT_HOST` in particular had no `${...}` substitution at all, so real-time/WebSocket features (Reverb, §12 architecture map) could never work off of `localhost`. Now `${VAR:-default}`, matching the existing pattern used for `APP_HOST_PORT` etc. — local dev defaults unchanged, production `.env` values now take effect. |
| 2026-07-23 | Fixed `setup.sh` gap: on a brand-new deploy, `APP_KEY` is empty in `.env` at container-creation time, so Compose's `env_file:` bakes an empty value into the container's process environment permanently. The entrypoint's `php artisan key:generate --force` (run after the container is already up) then fixes `.env` on disk, but phpdotenv won't override a variable already present in the OS environment — so Octane's worker crash-loops forever on `MissingAppKeyException` even though `.env` looks correct. `setup.sh` now generates `APP_KEY` (via `openssl rand -base64 32`) *before* `docker compose up`, the same way `DB_PASSWORD`/`MYSQL_ROOT_PASSWORD` are already handled. |
