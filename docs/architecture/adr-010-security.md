# ADR-010: Security

Status: Accepted

Date: 2026-07-19

Related: [ADR-003 Backend Architecture](./adr-003-backend-architecture.md) · [ADR-001 SaaS Multi-Tenancy](./adr-001-saas-multi-tenancy.md) · [ADR-008 Public API](./adr-008-public-api.md) · [ADR-011 Audit History](./adr-011-audit-history.md)

---

## Why

RetailPulse handles payment data, payroll/salary data, and — once Phase 28 lands — many tenants' data in one shared schema. Security here is not a checklist item bolted on before launch (Phase 16 "hardening" is a stabilization phase, not the *first* time security is considered); every ADR in this directory already has security consequences ([ADR-001](./adr-001-saas-multi-tenancy.md) tenant isolation, [ADR-011](./adr-011-audit-history.md) audit trail, [ADR-008](./adr-008-public-api.md) token scoping). This ADR states the cross-cutting principles that apply regardless of module, and is the reference point for RetailPulse's compliance posture as it pursues enterprise customers who will ask for one.

## What

RBAC/Policy authorization, tenant isolation, encryption, rate limiting, and secure-by-default configuration are non-negotiable, applied uniformly, and owned centrally rather than reimplemented per feature.

## How

### RBAC and policies are the only authorization mechanism

- **Spatie Laravel Permission** is the roles/permissions store. Roles may be scoped per branch where the business rule requires it (a Branch Manager role's authority is naturally branch-local).
- **`UserPermissionOverride`** allows per-user grants/revocations layered on top of role permissions for the exceptional case — it is not a substitute for defining a new role when a permission pattern is shared by more than one or two users.
- **Every Eloquent model that is a first-class authorizable resource has a Policy** (`app/Policies/`, one per resource — the codebase already has 60+, one per domain entity from `AccountMappingPolicy` through `WarehousePolicy`). A new controller action that mutates or reveals a resource must check a Policy, either via `FormRequest::authorize()` or an explicit `$this->authorize(...)` — never an inline `if ($user->hasRole('admin'))` check scattered in a controller, which bypasses the single point of truth a Policy provides and can't be reasoned about or tested in isolation.
- Authorization is enforced **server-side, always**. Frontend permission checks (`useCan()`, see [ADR-004](./adr-004-frontend-architecture.md)) exist purely to hide/disable UI a user can't use — they are never the actual authorization boundary, and a backend endpoint must reject an unauthorized action even if the frontend would never have shown the button for it.

### Tenant isolation is a security boundary, not a convenience filter

Once Phase 28 lands, `TenantScope` ([ADR-001](./adr-001-saas-multi-tenancy.md)) is a security control, not just a query convenience — a bug that lets one tenant's request read or write another tenant's row is a security incident, treated with the same severity as an authorization bypass. Any code that calls `withoutGlobalScope(TenantScope::class)` must be reviewed as carefully as a `sudo` call — it is reserved for the platform admin console, never for a code path a regular tenant request can reach.

### Encryption and secrets

- Sensitive columns (payment credentials, third-party API secrets, anything a Category 2 tenant-owned business record ([ADR-001](./adr-001-saas-multi-tenancy.md)) shouldn't expose in a database dump or backup) use Laravel's encrypted casts, not application-level ad hoc encryption.
- Provider credentials (Stripe keys, FBR credentials, gateway configs) live in `system_settings`/`payment_gateway_configs` or `.env`, never hardcoded in source, and are never logged — a debug log line must not print a full credential payload even in local development, since logs frequently outlive the environment they were written for.
- `.env` values are never committed; required values are documented (see `CLAUDE.md`'s Environment section) rather than given real defaults in version control.
- Data in transit uses TLS in every non-local environment ([ADR-018](./adr-018-deployment.md)); local development over plain HTTP is the one accepted exception.

### Rate limiting

- Public API tokens ([ADR-008](./adr-008-public-api.md)) get per-token quotas in addition to standard Laravel rate limiting on auth-sensitive routes (login, PIN verification) to resist brute force — POS PIN lockout (5 attempts → 15-minute lockout) is the existing precedent to replicate for any new PIN/secret-verification endpoint.
- Webhook receivers ([ADR-007](./adr-007-integration-hub.md)) verify signatures **before** any rate-limit-exempt processing, so an attacker cannot use an unauthenticated webhook endpoint as a free processing amplifier.

### Secure defaults

- New optional integrations/features default to **disabled** until explicitly configured (FBR reporting, payment gateways, workflow definitions all follow this pattern already) — a business must opt in to exposing more surface area, rather than opting out of something on by default.
- New API endpoints default to requiring authentication and an explicit ability/permission check; "public" is an explicit, reviewed decision (like the invoice public-token share link), never the unexamined default.
- New middleware stacks for admin/API routes include `auth`, the relevant module-enabled gate, and (post-Phase-28) tenant context resolution, by default — a route that skips one of these must have a documented reason (e.g. the FBR/Stripe webhook receivers, which are necessarily unauthenticated but signature-verified instead).

### Audit as a security control

Every mutation to a security-relevant model (`User`, `Role`, `Permission`, `Branch`, settings) is captured by the universal audit mechanism ([ADR-011](./adr-011-audit-history.md)) — a privilege escalation, a permission grant, or a branch reassignment is always reconstructable after the fact, which is itself a control against insider misuse, not just a debugging aid.

### OWASP Top 10 — how RetailPulse's existing patterns map to it

This is a checklist for reviewing new code against known web-application risk categories, not a claim of certification:

| Risk | RetailPulse's mitigation |
| :--- | :--- |
| Broken access control | Policy-per-resource + server-side enforcement, above; tenant scope post-Phase-28 |
| Cryptographic failures | Encrypted casts for sensitive columns; TLS in transit; no secrets in source/logs |
| Injection | Eloquent/query builder parameter binding everywhere — no raw string-concatenated SQL; FormRequest validation on all input ([ADR-003](./adr-003-backend-architecture.md)) |
| Insecure design | Threat/authorization modeling happens at Policy + Service design time, not retrofitted — see "secure defaults" above |
| Security misconfiguration | Secure defaults (above); `.env`-based config with documented required values, never real secrets committed |
| Vulnerable/outdated components | Composer/npm dependencies kept current; Laravel/PHP versions tracked deliberately (currently Laravel 13, PHP 8.3+) |
| Identification & auth failures | Laravel Breeze session auth + Sanctum token abilities for the public API; PIN lockout for POS; 2FA scoped to Phase 16 |
| Software/data integrity failures | Audit trail ([ADR-011](./adr-011-audit-history.md)); webhook signature verification ([ADR-007](./adr-007-integration-hub.md)) before trusting inbound payloads |
| Logging & monitoring failures | Universal audit logging; `php artisan pail` / structured logs for operational visibility ([ADR-018](./adr-018-deployment.md)) |
| Server-side request forgery | Outbound integration adapters ([ADR-007](./adr-007-integration-hub.md)) call fixed, configured provider endpoints — never a user-suppliable URL fetched server-side without allow-listing |

A new feature that touches authentication, file upload, external URL fetching, or raw query construction should be checked against this table before merge, not just functionally tested.

### Compliance readiness

RetailPulse does not claim a specific compliance certification today, but is built so that pursuing one (SOC 2, PCI-DSS scope reduction, GDPR-style data-subject rights) is a gap-closing exercise, not a rearchitecture:

- **Data minimization and retention** — Category-based data classification ([ADR-001](./adr-001-saas-multi-tenancy.md), [ADR-015](./adr-015-database-standards.md)) already distinguishes tenant-owned business data from platform/reference data, which is the same classification a data-subject-rights or retention-policy exercise needs as its starting point.
- **PCI-DSS scope reduction** — payment gateway adapters ([ADR-007](./adr-007-integration-hub.md)) are designed so raw card data is handled by the gateway's own hosted fields/tokenization where the provider supports it, not stored or transmitted through RetailPulse's own servers — a new payment adapter must preserve this, not take a shortcut that pulls raw card data into RetailPulse's request path.
- **Auditability** — the universal audit trail ([ADR-011](./adr-011-audit-history.md)) is the same evidence a SOC 2-style audit would ask for ("show me who changed this and when"); it is maintained for its own sake, not as compliance theater, which is exactly what makes it credible when compliance is eventually pursued formally.
- **Tenant data isolation** — [ADR-001](./adr-001-saas-multi-tenancy.md)'s row-level isolation plus the option of dedicated deployments for customers who require physical separation covers the two isolation postures most enterprise security questionnaires ask about.

## Trade-offs

- **Central Policy enforcement adds a mandatory step to every new authorizable resource** (you cannot ship a shortcut "just this once" without a Policy) — accepted because the alternative (ad hoc role checks) is exactly how authorization drift and privilege-escalation bugs accumulate at scale.
- **Signature verification and quota enforcement add latency and complexity to every inbound integration path** — accepted as strictly cheaper than the cost of a single forged webhook or a single runaway token degrading the shared platform.
- **Secure-by-default (disabled integrations, required auth) means more configuration steps for a business to turn a feature on** — accepted as the correct trade against a new tenant's business being exposed by a feature they never intended to enable.

## Alternatives considered

- **Middleware-only authorization (no Policy classes, just route middleware checking roles)** — rejected: doesn't scale to per-instance authorization (e.g. "can this user edit *this specific* purchase order," not just "can this role ever edit purchase orders"), which Policies handle natively via model-instance methods.
- **Field-level encryption for all columns "to be safe"** — rejected as blanket over-application: it would make ordinary reporting/query performance far worse for data that isn't actually sensitive (see [ADR-014](./adr-014-performance.md)), and dilutes the signal of which columns are genuinely sensitive. Encryption is targeted at Category-classified sensitive data, not applied uniformly.
- **Outsourcing PCI scope entirely to a hosted checkout page (redirect-based payment) instead of gateway adapters with hosted fields** — considered as a lower-effort PCI posture; not adopted as the only option because it would conflict with the in-app POS/checkout UX (Phase 8) that RetailPulse's core product experience depends on. Hosted-fields tokenization (above) is the compromise that keeps the UX in-app while still keeping raw card data off RetailPulse's servers.

## Future direction

As RetailPulse pursues larger enterprise customers, the compliance-readiness posture above is expected to formalize into an actual audited certification (most likely SOC 2 Type II, given the SaaS ERP target market) — at that point, this ADR's OWASP/compliance sections become the internal checklist an external auditor's control list is reconciled against, not a replacement for engaging that formal process.

## Impact on future development

- "Who can do X" always has exactly one authoritative answer: the Policy for that resource, plus any active `UserPermissionOverride` — never a patchwork of controller-level role checks that can drift out of sync with the Policy.
- A tenant isolation bug is triaged and fixed with incident-level urgency once Phase 28 ships, not treated as an ordinary data bug.
- Security-relevant defaults (disabled integrations, required auth, signature verification) are the starting point for every new feature, not a hardening pass applied retroactively.
