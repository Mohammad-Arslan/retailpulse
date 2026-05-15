# Phase 16 — Hardening, Offline Sync & Deployment

**SRS Reference:** §4.2–4.8, §7  
**Status:** Planned  
**Depends on:** All feature phases

---

## Objective

Production **non-functional requirements**: 2FA, session management, offline POS sync, caching, i18n, compliance, and DevOps.

## Workstreams

### Security & Auth (§3.1, §4.4)
- Mandatory 2FA for Super Admin, Owner, Accountant (TOTP + backup codes)
- Session/device list + remote revoke
- Magic link login (optional)
- Encrypt sensitive columns at rest
- Security event log on lockout

### Offline POS (§4.2)
- Complete IndexedDB queue + service worker sync
- Conflict resolution UI when stock changed during offline sale

### Performance (§4.6)
- Redis cache for permissions, products, config; cache tags
- Queue workers (Supervisor) for exports, webhooks, notifications
- DB index audit + `EXPLAIN` checklist

### Compliance (§4.3)
- Audit log immutability (no delete policy)
- Data retention documentation

### i18n (§4.8)
- Laravel lang files + react-i18next for all UI strings
- RTL-ready CSS where applicable

### SaaS readiness (§4.8)
- `tenant_id` scoping middleware (nullable = single tenant)

### DevOps (§7)
- Docker Compose (app, mysql, redis, reverb)
- CI: test, pint, build assets
- Staging/production env templates
- Backup strategy for MySQL

## Acceptance Criteria

1. Super Admin forced to enable 2FA on first login after deploy.
2. Offline sale syncs on reconnect; conflict flagged when stock insufficient.
3. Permission check served from cache < 5ms p95.
4. One-command deploy documented in `docs/deployment.md`.
