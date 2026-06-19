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

---

## Phase Enhancements (SRS v4.0 — baseline)

### Enterprise Security Controls (§4.4)

**IP & Geo Restrictions**
- `ip_restrictions` table: `id`, `scope_type` (`global`/`branch`/`role`), `scope_id`, `mode` (`allowlist`/`blocklist`), `cidr_ranges` (JSON array).
- `IpRestrictionMiddleware` checks the request IP against the applicable rules; denied requests receive a 403 with an audit log entry.
- Admin UI: IP Rules management under Security Settings.

**Device Binding**
- `trusted_devices` table: `id`, `user_id`, `device_fingerprint`, `device_name`, `trusted_at`, `last_seen_at`, `status`.
- On first login from an unknown device, a one-time verification code is sent via email/SMS; on approval the device is bound.
- POS terminals can be pre-registered as branch-trusted devices by a manager; all cashiers at that branch inherit terminal trust.

**Session Expiry Configuration**
- `system_settings` keys: `security.idle_timeout_minutes` (default 30 for cashier, 120 for admin), `security.absolute_session_hours` (default 12), `security.max_concurrent_sessions` (default 3 per user).
- Idle timeout enforced client-side via JS timer + server-side session TTL.

**Password Policy Enforcement**
- `system_settings` keys: `security.password_min_length` (default 10), `security.password_require_uppercase`, `security.password_require_digit`, `security.password_require_symbol`, `security.password_expiry_days` (0 = never), `security.password_history_count` (default 5).
- Enforced in `PasswordPolicyService` called from both the registration and password change flows.
- Breached password check via HaveIBeenPwned k-anonymity API (optional, configurable).

**Data Retention Scheduler**
- `data_retention_policies` table: `entity_type`, `retention_days`, `archive_mode` (`delete`/`export_then_delete`/`anonymise`).
- Nightly `DataRetentionJob` archives or purges records older than the configured threshold.
- Exemption: `audit_logs` are never pruned unless a specific compliance override is set by Super Admin.

### Modular Feature Flag Middleware Gate
- `CheckModuleEnabled` middleware class registered as a named middleware alias `module`.
- Applied to all route groups belonging to optional modules: `Route::middleware(['auth', 'module:restaurant'])->group(...)`.
- Returns HTTP 404 (not 403) when a module is disabled — prevents information leakage about disabled features.
- Pairs with the dynamic sidebar (Phase 23) which already hides the navigation links; the middleware provides server-side enforcement.

---

## SRS v4.0 Enhancements (§4.9–4.10, §7)

### Disaster Recovery & Business Continuity (§4.9)

- **RTO:** Full restoration within 4 hours; POS-only mode within 1 hour.
- **RPO:** Maximum 1 hour data loss; incremental backups every 60 minutes to geographically separate S3-compatible storage.
- Nightly full snapshots + MySQL binlog shipping.
- Monthly restore drills documented in `docs/deployment.md`; verify 4-hour RTO.
- MySQL Group Replication or RDS Multi-AZ failover documented.
- POS offline cap: 8 hours during complete server unavailability (§4.2).

### Testing Strategy (§4.10)

- **Unit tests:** 90%+ line coverage on Services, Repositories, DTOs (Pest); CI coverage gate blocks merge below threshold.
- **Integration tests:** E2E on checkout, COGS posting, GL journals, payroll, WHT deduction — assert resulting journal entries and stock movements.
- **Accounting invariant tests:** Balanced double-entry on sale, return, purchase receive, payment, payroll, intercompany transfer, landed cost allocation.
- **UAT:** Product owner sign-off per phase acceptance criteria before production deploy.
- **Load testing:** 100 TPS for 30 minutes on staging; p95 POS search < 200ms with 1M variants / 100K customers — gate before go-live.
- **Regression suite:** All bug fixes include regression test; CI blocks on failure.

### GDPR Stub (§4.4)

- **`customer_consents`** table — marketing, profiling, data-sharing consent with timestamps and source.
- Right-to-erasure process (anonymize PII, retain transactions); customer data export JSON/CSV.
- Full GDPR implementation deferred; schema prepared from initial build.

### Expanded DevOps (§7)

- CI pipeline: lint → unit → integration → 90% coverage → build → staging → load test gate → production.
- Monitoring alerts: p95 > 500ms, queue depth > 1000, failed jobs > 10, replication lag > 30s.
- Secrets in AWS Secrets Manager or HashiCorp Vault — never in version control for production.

### Acceptance Criteria (v4.0)

1. Monthly restore drill completes within documented RTO on staging.
2. CI fails when service coverage drops below 90%.
3. Accounting invariant test suite passes on full test DB seed.
4. Load test report shows 100 TPS sustained with p95 search < 200ms.
