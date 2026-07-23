# ADR-018: Deployment

Status: Accepted

Date: 2026-07-19

Related: [ADR-001 SaaS Multi-Tenancy](./adr-001-saas-multi-tenancy.md) · [ADR-014 Performance](./adr-014-performance.md) · [ADR-010 Security](./adr-010-security.md) · [Phase 16 — Hardening & Deployment](../phases/phase-16-hardening-deployment.md)

---

## Why

RetailPulse today runs entirely as a local development setup (Laragon, SQLite, `composer dev` running the Laravel server/queue/Vite/Reverb concurrently) — there is no CI pipeline, no containerization, and no documented production topology yet. That is appropriate for the current build phase, but the SaaS vision ([ADR-001](./adr-001-saas-multi-tenancy.md)) and the explicit Phase 16 hardening scope (SRS §4.9–§4.10, §7: disaster recovery, load testing, CI/CD) mean deployment cannot remain an afterthought once real tenants depend on uptime and data durability. This ADR states the deployment principles now, so environment/infrastructure decisions made incrementally before Phase 16 don't have to be unwound.

## What

Three environment tiers (development, staging, production) with increasing rigor; a shared-SaaS deployment as the default topology with dedicated deployments as an explicit, supported option for enterprise customers; CI/CD, monitoring, and disaster-recovery targets defined by Phase 16 as the production bar RetailPulse is building toward.

## How

### Environments

- **Development** — local (Laragon or equivalent), SQLite default database, `composer dev` running Laravel server + queue worker + Vite + Reverb concurrently. No production credentials, no production data. This is the only environment where `APP_ENV=local`-gated debug surfaces (e.g. the dev AI smoke endpoint, per [ADR-017](./adr-017-ai-architecture.md)) are reachable.
- **Staging** — a production-shaped environment (same database engine as production, same queue/cache drivers, containerized per Phase 16) used for pre-release verification, load testing, and restore drills. Staging never contains real tenant data — it is seeded/synthetic, so a staging incident or a shared-access mistake cannot leak real customer information.
- **Production** — the live, multi-tenant (post-Phase-28) SaaS environment. Every change reaching production has passed through staging; there is no direct-to-production deployment path for application code.

### Deployment topology — shared SaaS by default, dedicated by exception

Consistent with [ADR-001](./adr-001-saas-multi-tenancy.md)'s tenancy model: the default and primary deployment is one shared application instance and shared database serving all tenants. **Dedicated deployments** (a separate database, or a fully separate instance) remain a supported, explicit option for enterprise customers who require physical isolation for their own compliance reasons — this is a deployment-topology choice layered on the same codebase and schema, not a fork or a separately maintained version. A dedicated deployment runs the identical application image/release as the shared SaaS environment; it does not get a divergent code branch.

### CI/CD (Phase 16 target)

- Pipeline stages: lint (`./vendor/bin/pint`) → unit tests → integration tests → coverage gate → asset build → deploy to staging → load test gate → deploy to production ([ADR-013](./adr-013-testing-strategy.md) for what each test tier covers).
- A failing stage blocks progression to the next — there is no "deploy anyway" override for a failing gate on the path to production.
- Docker Compose (app, database, Redis, Reverb) is the target local-parity and staging environment shape, so "works on my machine" differences are minimized between development and where the app actually runs in staging/production.

**Current implementation state (2026-07-23):** `.github/workflows/ci.yml` runs lint (Pint) → asset build (`npm run build`) → tests (`composer test`, SQLite in-memory per `phpunit.xml`, no service containers needed) on every push/PR to `main`. `.github/workflows/deploy.yml` auto-deploys to the staging VPS (`git pull` + `bash setup.sh production --rebuild` + cache re-warm) after CI succeeds on `main`. This is a **partial** implementation of the target above — no coverage gate (deliberately: `.ai/rules/testing.mdc` calls out "no gameable percentage-only mindset before Phase 16 CI gates"), no integration-test tier separate from Feature tests, no load-test gate, no staging-vs-production two-tier deploy (there is currently one VPS, treated as staging), and no secrets manager (secrets live in GitHub Actions repo secrets + the VPS's `.env`, not Vault/AWS Secrets Manager). Closing that remaining gap is still Phase 16 scope.

### Caching, queues, and background workers in production

Per [ADR-014](./adr-014-performance.md): the database-driver cache/queue used in development is expected to become Redis in every staging/production deployment, with queue workers supervised (e.g. Supervisor) rather than run ad hoc — a queue worker dying silently under load is a production incident, not a background inconvenience.

### Monitoring and alerting

Production is expected to alert on: p95 request latency exceeding a threshold, queue depth growing unbounded, failed-job count exceeding a threshold, and (once a replica exists) replication lag — these are the concrete signals Phase 16 identifies as indicating the platform is degrading before it becomes a full outage. Structured logging (`php artisan pail` locally; a centralized log sink in production) is the baseline observability every environment tier above local development is expected to have.

### Secrets management

Local development uses `.env` (never committed, per [ADR-010](./adr-010-security.md)). Staging and production secrets are expected to live in a dedicated secrets manager (e.g. AWS Secrets Manager, HashiCorp Vault) rather than environment files checked into any deployment tooling repository — this is a Phase 16 hardening deliverable, treated as a hard requirement before production go-live with real tenant data, not an optional improvement.

### Disaster recovery

Phase 16 sets concrete targets: **RTO** (recovery time objective) of full restoration within 4 hours, POS-only-mode restoration within 1 hour; **RPO** (recovery point objective) of at most 1 hour of data loss, achieved via nightly full snapshots plus incremental/binlog-based backups shipped to geographically separate storage. These targets are validated by **monthly restore drills**, documented in a deployment runbook — an untested backup is not a backup, it's an unverified assumption. POS is designed to tolerate up to 8 hours of complete server unavailability via its offline queue (Phase 7/16) as the bridge during a restoration window.

### Backward compatibility during deploys

Because RetailPulse is a shared-schema SaaS ([ADR-001](./adr-001-saas-multi-tenancy.md)), a deploy is never a coordinated "stop everyone, migrate, restart" event once real tenants are live — migrations are additive/expand-first ([ADR-015](./adr-015-database-standards.md)) specifically so a rolling deploy (old code and new code briefly running against the same, already-migrated schema) never breaks. This constraint is designed in from Phase 16 onward, not bolted on when the first zero-downtime deploy requirement appears.

## Trade-offs

- **Dedicated deployments for enterprise customers mean maintaining (a small number of) separate running instances**, not just one shared fleet — accepted as a deliberate, paid-for exception for customers who need it, not the default posture; it does not change the codebase or require a separate maintained branch.
- **A hard CI gate (coverage, load test) that blocks "deploy anyway" slows down urgent hotfixes** — accepted because the alternative (a bypass path that gets used under pressure) is exactly how a hardened deployment pipeline erodes back into an ungated one; an urgent fix still goes through the pipeline, just with priority, not around it.
- **Monthly restore drills and DR infrastructure (geo-separate backup storage, binlog shipping) are ongoing operational cost** even when nothing has gone wrong — accepted because an RTO/RPO target that has never been tested is not a real target, and the cost of discovering a backup doesn't restore correctly *during* an actual incident is far higher.

## Alternatives considered

- **Single-tenant-per-deployment (every customer gets their own instance) as the default** — rejected per [ADR-001](./adr-001-saas-multi-tenancy.md)'s reasoning: doesn't scale operationally to the target tenant count, and reserved instead as the dedicated-deployment exception for customers who specifically need it.
- **No staging environment — test in production with feature flags** — rejected: RetailPulse's business-critical data (financial, payroll) makes production experimentation an unacceptable risk; staging with synthetic data plus load testing is the safer gate before real tenant exposure.
- **Continuous deployment straight to production on every merge (no staging gate)** — rejected as the default: appropriate for some product categories, not for a financial/HR ERP where a bad deploy has compliance and trust consequences beyond a typical SaaS outage.

## Future direction

As Phase 16 is implemented, the CI/CD pipeline, Docker Compose environment, and DR drills described above move from "documented target" to "actual, running infrastructure" — at that point, this ADR's Current State should be updated to reflect what's live versus still planned (see this directory's [README](./README.md) for the ADR-update process). Post-Phase-28, deployment topology decisions increasingly interact with [ADR-001](./adr-001-saas-multi-tenancy.md)'s tenant lifecycle (provisioning a new tenant, or a new dedicated deployment, should itself become a repeatable, largely automated operation rather than a manual one).

## Impact on future development

- New migrations and schema changes are written expand-first from now on, specifically so the zero-downtime rolling-deploy requirement this ADR anticipates doesn't require retrofitting migration discipline later ([ADR-015](./adr-015-database-standards.md)).
- New infrastructure-touching work (a new queue, a new cache usage, a new scheduled job) is designed assuming Redis/Supervisor-backed production infrastructure, not just the SQLite/database-driver development default ([ADR-014](./adr-014-performance.md)).
- A feature that would be hard to restore correctly from a nightly-snapshot-plus-binlog backup (e.g. relying on ephemeral local disk state) is a design flag to resolve before it ships, not a surprise discovered during a real restore drill.
