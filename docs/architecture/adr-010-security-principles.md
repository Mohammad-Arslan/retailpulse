# ADR-010: Security Principles

Status: Accepted

Date: 2026-07-19

Related: [ADR-004 Layered Architecture](./adr-004-layered-architecture.md) · [ADR-001 SaaS Multi-Tenancy](./adr-001-saas-multi-tenancy.md) · [ADR-008 Public API](./adr-008-public-api.md)

---

# Context

RetailPulse handles payment data, payroll/salary data, and — once Phase 28 lands — many tenants' data in one shared schema. Security here is not a checklist item bolted on before launch (Phase 16 "hardening" is a stabilization phase, not the *first* time security is considered); every ADR in this directory already has security consequences ([ADR-001](./adr-001-saas-multi-tenancy.md) tenant isolation, [ADR-005](./adr-005-audit-trail.md) audit trail, [ADR-008](./adr-008-public-api.md) token scoping). This ADR states the cross-cutting principles that apply regardless of module.

# Decision

## RBAC and policies are the only authorization mechanism

- **Spatie Laravel Permission** is the roles/permissions store. Roles may be scoped per branch where the business rule requires it (a Branch Manager role's authority is naturally branch-local).
- **`UserPermissionOverride`** allows per-user grants/revocations layered on top of role permissions for the exceptional case — it is not a substitute for defining a new role when a permission pattern is shared by more than one or two users.
- **Every Eloquent model that is a first-class authorizable resource has a Policy** (`app/Policies/`, one per resource — the codebase already has 60+, one per domain entity from `AccountMappingPolicy` through `WarehousePolicy`). A new controller action that mutates or reveals a resource must check a Policy, either via `FormRequest::authorize()` or an explicit `$this->authorize(...)` — never an inline `if ($user->hasRole('admin'))` check scattered in a controller, which bypasses the single point of truth a Policy provides and can't be reasoned about or tested in isolation.
- Authorization is enforced **server-side, always**. Frontend permission checks (`useCan()`, see [ADR-012](./adr-012-frontend-architecture.md)) exist purely to hide/disable UI a user can't use — they are never the actual authorization boundary, and a backend endpoint must reject an unauthorized action even if the frontend would never have shown the button for it.

## Tenant isolation is a security boundary, not a convenience filter

Once Phase 28 lands, `TenantScope` ([ADR-001](./adr-001-saas-multi-tenancy.md)) is a security control, not just a query convenience — a bug that lets one tenant's request read or write another tenant's row is a security incident, treated with the same severity as an authorization bypass. Any code that calls `withoutGlobalScope(TenantScope::class)` must be reviewed as carefully as a `sudo` call — it is reserved for the platform admin console, never for a code path a regular tenant request can reach.

## Encryption and secrets

- Sensitive columns (payment credentials, third-party API secrets, anything a Category 2 tenant-owned business record ([ADR-001](./adr-001-saas-multi-tenancy.md)) shouldn't expose in a database dump or backup) use Laravel's encrypted casts, not application-level ad hoc encryption.
- Provider credentials (Stripe keys, FBR credentials, gateway configs) live in `system_settings`/`payment_gateway_configs` or `.env`, never hardcoded in source, and are never logged — a debug log line must not print a full credential payload even in local development, since logs frequently outlive the environment they were written for.
- `.env` values are never committed; required values are documented (see CLAUDE.md's Environment section) rather than given real defaults in version control.

## Rate limiting

- Public API tokens ([ADR-008](./adr-008-public-api.md)) get per-token quotas in addition to standard Laravel rate limiting on auth-sensitive routes (login, PIN verification) to resist brute force — POS PIN lockout (5 attempts → 15-minute lockout) is the existing precedent to replicate for any new PIN/secret-verification endpoint.
- Webhook receivers ([ADR-007](./adr-007-integration-hub.md)) verify signatures **before** any rate-limit-exempt processing, so an attacker cannot use an unauthenticated webhook endpoint as a free processing amplifier.

## Secure defaults

- New optional integrations/features default to **disabled** until explicitly configured (FBR reporting, payment gateways, workflow definitions all follow this pattern already) — a business must opt in to exposing more surface area, rather than opting out of something on by default.
- New API endpoints default to requiring authentication and an explicit ability/permission check; "public" is an explicit, reviewed decision (like the invoice public-token share link), never the unexamined default.
- New middleware stacks for admin/API routes include `auth`, the relevant module-enabled gate, and (post-Phase-28) tenant context resolution, by default — a route that skips one of these must have a documented reason (e.g. the FBR/Stripe webhook receivers, which are necessarily unauthenticated but signature-verified instead).

## Audit as a security control

Every mutation to a security-relevant model (`User`, `Role`, `Permission`, `Branch`, settings) is captured by the universal audit mechanism ([ADR-005](./adr-005-audit-trail.md)) — a privilege escalation, a permission grant, or a branch reassignment is always reconstructable after the fact, which is itself a control against insider misuse, not just a debugging aid.

# Consequences

- "Who can do X" always has exactly one authoritative answer: the Policy for that resource, plus any active `UserPermissionOverride` — never a patchwork of controller-level role checks that can drift out of sync with the Policy.
- A tenant isolation bug is triaged and fixed with incident-level urgency once Phase 28 ships, not treated as an ordinary data bug.
- Security-relevant defaults (disabled integrations, required auth, signature verification) are the starting point for every new feature, not a hardening pass applied retroactively.
