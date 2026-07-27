# RetailPulse — Docker Security Audit (P7)

**Date:** 2026-07-27  
**Scope:** `Dockerfile`, `docker-compose.yml`, `docker-compose.ops.yml`, `docker-compose.observability.yml`, `docker/entrypoint.sh`, Supervisor configs, networks, volumes, health checks, secrets, Docker socket usage.  
**Related:** [ops-stack.md](./ops-stack.md), [deployment-guidelines.md](./deployment-guidelines.md), [ADR-010](./architecture/adr-010-security.md), [ADR-018](./architecture/adr-018-deployment.md).

This document is an audit with **recommended** hardening. Changes that would significantly complicate Contabo single-VPS deploys are listed as optional with tradeoffs — not silently applied.

---

## 1. Verdict

The core stack is already production-shaped for a single VPS: localhost port binds, named volumes, health checks, `restart: unless-stopped`, and a multi-stage FrankenPHP image. The highest residual risks are **Docker socket mounts** (Portainer/Jenkins/Promtail/cAdvisor), **running Supervisord as root inside the app container**, and **secrets in `.env`**. None of those are unusual for this topology; they need operational controls rather than a redesign.

---

## 2. Findings & recommendations

### 2.1 Dockerfile (app)

| Finding | Risk | Recommendation | Applied? |
| :--- | :--- | :--- | :--- |
| Production stage runs Composer as root during build | Low (build-time) | Acceptable; final runtime still elevated via Supervisord | No change |
| No non-root USER for Octane | Medium | FrankenPHP + Supervisord managing multiple processes as root is common; dropping privileges per-program is better than a blanket USER (Horizon/Reverb need careful uid). Optional: set `user=` on each Supervisor program to `www-data` after verifying socket permissions | Recommend only |
| `apt-get` installs Node in the base image | Low | Needed for entrypoint asset rebuilds; keep | No change |
| `--ignore-platform-reqs` | Low (known gap) | Documented in deployment guidelines; resolve PHP 8.3 vs lock separately | No change |

**Tradeoff:** Running Octane workers as `www-data` improves blast radius if RCE occurs in PHP, but Supervisord must remain root (or use rootless Podman). Complexity rises; do it when you dedicate time to a privilege-drop pass, not as a drive-by.

### 2.2 docker-compose.yml (core)

| Finding | Risk | Recommendation | Applied? |
| :--- | :--- | :--- | :--- |
| App ports bound to `127.0.0.1` | — | Correct (2026-07-23 fix) | Already done |
| MinIO API published on `0.0.0.0:9000` | Medium | Intentional for anonymous media; prefer Nginx `media.example.com` proxy and switch API bind to localhost when ready | Recommend (ops) |
| Default passwords in compose interpolation | High if unchanged | `setup.sh` / `.env` must use unique secrets in production | Process |
| Bind-mount `./:/var/www/html` in production | Medium | Enables hot deploys without rebuild; also means host file write = container write. Restrict host dir perms to `deploy:deploy` `750` | Recommend |
| Named volumes for vendor/build | Low | Self-heal via entrypoint hashes — good | Already done |
| `mailpit` / `phpmyadmin` in production compose | Medium | Disable or don't publish; localhost bind mitigates | Documented |
| No `read_only: true` / `security_opt` | Low–Medium | Optional `security_opt: [no-new-privileges:true]` on redis/mysql sidecars | Recommend |
| `MINIO_PROMETHEUS_AUTH_TYPE=public` | Low on Docker network | Prefer bearer token for MinIO metrics if the API port is internet-reachable | Config flag |

### 2.3 Entrypoint & Supervisor

| Finding | Risk | Recommendation | Applied? |
| :--- | :--- | :--- | :--- |
| `db:seed --force` on every production start | Medium (ops) | Idempotent seeders required; long-term: gate seed behind env `RUN_SEEDERS=true` | Future |
| Supervisord `user=root` | Medium | See 2.1 | Recommend |
| Logs to stdout | Positive | Enables Promtail/Docker logging | Keep |

### 2.4 Networks & volumes

| Finding | Risk | Recommendation | Applied? |
| :--- | :--- | :--- | :--- |
| Single bridge network `retailpulse` | Low | Acceptable for monolith VPS; optional internal networks for db-tier later | Recommend only |
| Explicit volume names | Positive | Survives project recreate | Keep |
| Ops volumes separate projects | Positive | Survive `--remove-orphans` | Applied (2026-07-27) |

### 2.5 Environment & secrets

| Finding | Risk | Recommendation | Applied? |
| :--- | :--- | :--- | :--- |
| Secrets in `.env` on disk | Medium | File mode `600`, owner `deploy`; Phase 16 → Vault/AWS SM per ADR-018 | Process |
| GitHub Actions SSH key | Medium | Deploy key with least privilege; rotate | Process |
| Grafana default `changeme` | High if exposed | Force change via `.env` before `ops-up` | Documented |
| MySQL exporter using root DSN | Medium | Create `exporter@'%'` with PROCESS/REPLICATION CLIENT/SELECT only | Recommend |
| Jenkins now holds the `retailpulse-vps-ssh` deploy key **and** `JENKINS_SMTP_PASSWORD` (2026-07-28: Jenkins became the deploy source of truth, see ADR-018) | Medium–High | Same key already had full sudo on the VPS via GitHub Actions before this change — blast radius is unchanged in kind, but is now reachable via anyone who can trigger/modify the Jenkins job, not just GitHub. Restrict Jenkins job-configuration permissions (matrix/role strategy, already in `plugins.txt`); rotate the SMTP credential like any other secret once real SMTP replaces Mailpit | Recommend |

Example monitoring user:

```sql
CREATE USER 'exporter'@'%' IDENTIFIED BY 'STRONG_PASSWORD';
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'%';
FLUSH PRIVILEGES;
```

Then set `MYSQL_EXPORTER_DSN=exporter:STRONG_PASSWORD@(mysql:3306)/`.

### 2.6 Docker socket usage

| Consumer | Mode | Risk | Mitigation |
| :--- | :--- | :--- | :--- |
| Portainer | RW socket | **Critical** if Portainer compromised | Localhost + TLS + strong admin + IP allowlist; optional socket proxy |
| Jenkins | RW socket | **Critical** if Jenkins compromised | Same; lock down who can run pipelines; prefer agents without socket for untrusted PRs |
| Promtail | RO socket | Low–Medium | RO is appropriate for log discovery |
| cAdvisor | Privileged + mounts | Medium | Localhost only; required for accurate container metrics |

**Socket proxy tradeoff (`tecnativa/docker-socket-proxy`):** shrinks API surface (e.g. deny `POST` to some endpoints) but Portainer still needs broad capabilities to be useful. Adds another service and failure mode. **Recommendation:** keep direct mounts for Portainer/Jenkins on a single-admin VPS; introduce a proxy when multiple operators share Portainer or when compliance requires API allowlisting.

**Never** expose Portainer/Jenkins/Grafana/Prometheus/cAdvisor on `0.0.0.0`.

### 2.7 Health checks

Core and ops services define health checks. Gaps (acceptable):

- `mailpit` / `phpmyadmin` — no healthcheck (non-critical)  
- `minio-init` — one-shot  

### 2.8 CI/CD interaction

`deploy.yml` SSHes and runs `setup.sh production --rebuild`. Because ops uses projects `retailpulse-ops` / `retailpulse-obs`, deploy **does not** stop Portainer/Jenkins/monitoring. Do **not** merge ops services into the default Compose file without removing `--remove-orphans` or always passing both files.

---

## 3. Hardening checklist (operator)

- [ ] All `.env` secrets unique; `APP_DEBUG=false`  
- [ ] UFW: only 22/80/443 public  
- [ ] Ops UIs only via Nginx + TLS (+ IP allowlist)  
- [ ] Portainer admin created; 2FA if available  
- [x] Jenkins: disable signup; use matrix/role strategy — automated via `docker/jenkins/security.groovy` when `JENKINS_ADMIN_USER`/`JENKINS_ADMIN_PASSWORD` are set (2026-07-28); falls back to the manual wizard (still no anonymous access) if they're left blank  
- [ ] Jenkins: protect `DEPLOY_PRODUCTION` (restrict who can trigger/edit the job beyond the sole admin above, once more than one operator has Jenkins access)  
- [ ] Grafana admin password changed; anonymous auth off (default in compose)  
- [ ] MySQL exporter dedicated user  
- [ ] Contabo snapshots before first ops enable  
- [ ] Document who has SSH + Portainer + Jenkins admin  

---

## 4. What we deliberately did **not** change

| Idea | Why skipped now |
| :--- | :--- |
| Rootless Docker / Podman | Large operational shift on Contabo |
| Split DB network + internal-only MySQL | Good later; needs careful Compose rewrite |
| Secrets manager | Phase 16 / ADR-018 |
| Removing bind-mount in production | Breaks current deploy ergonomics |
| DinD for Jenkins | Heavier than socket; worse for Contabo disk/CPU |

---

## Document history

| Date | Change |
| :--- | :--- |
| 2026-07-28 | Flagged elevated Jenkins blast radius now that it holds deploy authority (§2.5) — same VPS SSH key as before, more paths to trigger it |
| 2026-07-27 | Initial production-grade Docker security audit for core + ops + observability stacks |
