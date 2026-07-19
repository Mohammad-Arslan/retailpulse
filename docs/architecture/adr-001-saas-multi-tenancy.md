# ADR-001: SaaS Multi-Tenancy Strategy

Status: Accepted

Date: 2026-07-19 (originally 2026-07-18)

Related: [ADR-002 Modular Monolith](./adr-002-modular-monolith.md) · [ADR-010 Security](./adr-010-security.md) · [ADR-015 Database Standards](./adr-015-database-standards.md) · [Phase 28 — SaaS Multi-Tenancy](../phases/phase-28-saas-multitenancy.md)

---

## Why

RetailPulse's long-term vision ([`docs/vision.md`](../vision.md)) is a single SaaS instance serving thousands of retail/restaurant businesses, competing with the multi-tenant SaaS tiers of NetSuite, Dynamics 365, and Odoo — not a per-customer install. That target is incompatible with deferring tenancy to "whenever we get to it": retrofitting tenant isolation onto tables and query paths built without it in mind, after hundreds of tables and dozens of modules already exist, is one of the most expensive refactors a mature codebase can undergo (every unscoped query is a potential cross-tenant leak to hunt down one at a time). RetailPulse instead prepares its schema for tenancy continuously, from early development, while deferring the actual *enforcement* infrastructure to a scheduled phase — cheap now, prohibitively expensive later.

## What

RetailPulse adopts **shared database, shared schema, row-level isolation**:

- One application instance, one database, serving every tenant.
- Every tenant-owned table carries a `tenant_id` column.
- Isolation is enforced by a global query scope keyed on the current request's resolved tenant — not by separate databases or separate schemas per tenant.

Phase 28 implements the complete enforcement infrastructure (tenant resolver, context, global scope, middleware, tenant-aware queues/cache/storage/search/broadcasting — see below). Before Phase 28, the codebase prepares for this without enforcing it. Phase 29 is Workflow Engine — do not conflate.

## How

### Phase strategy

**Before Phase 28 (current state):** the application operates as a single-tenant ERP. Schema is prepared — nullable `tenant_id` columns exist on tenant-owned tables — but no filtering, middleware, resolver, or tenant-scoped security exists yet. The sole purpose of schema prep is to avoid a disruptive backfill later.

**Phase 28 delivers:**

- Tenant Resolver — resolves the active tenant from subdomain or an explicit header (see Phase 28 doc for the concrete `SetTenantContext` design)
- `TenantContext` — request-lifecycle singleton holding the resolved tenant, the same architectural pattern as the existing `BranchContext` ([ADR-002](./adr-002-modular-monolith.md))
- Global Tenant Scope (`TenantScope`) — auto-applied to every tenant-owned Eloquent model
- Tenant Middleware, Tenant Authorization, Tenant-aware Validation
- Tenant-aware Queues, Cache, Storage, Search, Broadcasting — a queued job, a cache key, a stored file, a search index entry, and a Reverb channel must all be resolvable back to one tenant; none of these subsystems get a free pass just because they sit outside the request/response cycle
- Tenant Provisioning and Tenant Lifecycle Management (below)
- Cross-Tenant Data Protection

After Phase 28, tenant isolation is mandatory for every new table and every new subsystem — not optional, not "add it later."

### Current state in codebase

Schema preparation is complete: **170 tables** carry a nullable `tenant_id` (17 already had the column; migration `2026_07_19_140000_add_nullable_tenant_id_for_saas_schema_prep` added it to 153 more). Full table lists, exclusions, and notes: [tenant-schema-preparation.md](./tenant-schema-preparation.md). There is still no `tenants` table, no `TenantContext` / `TenantScope` / `SetTenantContext` middleware, and no tenant filtering enforced anywhere. **Do not treat the presence of a `tenant_id` column as evidence that isolation is enforced** — grep for `TenantScope` before assuming it exists, and do not add ad hoc tenant-filtering logic ahead of Phase 28 without an explicit decision to pull that scope forward.

### Tenant classification rules

**Category 1 — Tenant Root** (defines ownership; must contain `tenant_id`): Organizations, Organization Entities, Branches, Users (if tenant-owned).

**Category 2 — Tenant-Owned Business Data** (every business record owned by a tenant gets a *direct* `tenant_id`, even when it's derivable through a join — cheaper isolation checks, better indexing, faster reporting, easier sharding/export/backup, simpler future migration): Accounting, HR, Payroll, Inventory, Procurement, Sales, CRM, Manufacturing, Assets, POS, Loyalty, Projects, Helpdesk.

**Category 3 — Platform Infrastructure** (never `tenant_id`): migrations, jobs, failed_jobs, cache, sessions, password reset tokens.

**Category 4 — Global Reference Data** (never `tenant_id`, shared by all tenants): Countries, Currencies, Languages, Timezones. **Note:** Units of Measure (`units`) already carries `tenant_id` in this codebase (treated as a tenant catalog). Phase 28 should decide whether to keep that or demote `units` to true global reference — see [tenant-schema-preparation.md](./tenant-schema-preparation.md).

**Pivot tables** do not automatically get `tenant_id` — only add it when the pivot behaves as an independent business entity or is queried independently of its parents.

**Development rule:** every new migration is classified before creation. Ask: (1) Is this business data owned by one tenant? (2) Is this platform infrastructure? (3) Is this shared reference data? (4) Is this an independent business entity queried on its own? Only tenant-owned business data (answer 1 or 4) receives `tenant_id`.

### Tenant lifecycle

A tenant moves through a defined lifecycle, mirrored by `tenants.status` (Phase 28: `trial → active → suspended/cancelled`):

1. **Provisioning** — a new tenant is created (self-service signup or platform-admin-created), assigned a subdomain, and seeded with the baseline RBAC roles ([ADR-010](./adr-010-security.md)) and default `system_settings`. No tenant's provisioning step may touch another tenant's data — provisioning is itself the first tenant-isolation boundary to get right.
2. **Onboarding** — the wizard (business info → branch setup → plan/module selection → team invite → go-live, per Phase 28) populates the tenant's first real records. Nothing about onboarding is special-cased outside the normal tenant-scoped write path — it exercises the same `TenantScope`-protected models as every subsequent request.
3. **Active** — normal operation; usage metered against plan limits (`tenant_usage_metrics`), soft-warned at 90% and soft-blocked at 100% of a limited resource.
4. **Suspended** — access blocked (HTTP 402 at the middleware layer per the Phase 28 design) without deleting data; a suspended tenant's data remains intact and isolated, ready to reactivate on payment recovery.
5. **Cancelled** — the tenant stops being billed and loses access; data retention/export/purge policy for a cancelled tenant is a compliance decision to be finalized alongside Phase 28 (see [ADR-010](./adr-010-security.md) for the general retention posture) — do not hard-delete a cancelled tenant's rows without that policy being explicit.
6. **Platform admin override** — the platform console (`tenant_id = null`, `is_platform_admin = true`) can inspect, impersonate, suspend, or reinstate a tenant. Every such action is audited ([ADR-011](./adr-011-audit-history.md)) with the same rigor as any other privileged action, because it crosses the tenant-isolation boundary by design.

## Trade-offs

**Accepted, in exchange for shared-schema's operational simplicity:**
- A single schema bug (a missing `TenantScope` on a new model, a raw query that forgets the tenant filter) risks a cross-tenant data leak — this is why tenant isolation is treated as a security control ([ADR-010](./adr-010-security.md)), not a convenience filter, and why `withoutGlobalScope(TenantScope::class)` is restricted to the platform console.
- Noisy-neighbor risk: one tenant's heavy usage (a large import, a reporting query) can affect shared database performance for others, mitigated by usage metering/limits (Phase 28) and query optimization discipline ([ADR-014](./adr-014-performance.md)), not by physical isolation.
- Schema migrations apply to all tenants simultaneously — there is no per-tenant migration version to manage, which is a benefit for maintenance velocity but means a breaking migration is breaking for everyone at once; migrations must be backward-compatible in the same release they ship in (expand-then-contract, never a single non-additive change).

## Alternatives considered

- **Database-per-tenant** — strongest isolation, simplest mental model for "this tenant's data cannot leak," but operationally prohibitive at RetailPulse's target scale (thousands of tenants means thousands of databases to migrate, back up, and monitor) and defeats cheap horizontal scaling of the application tier. Reserved as an option only for dedicated/enterprise deployments that explicitly pay for isolated infrastructure (see Long-Term Vision below) — not the default.
- **Schema-per-tenant** (one Postgres schema or MySQL database per tenant, shared server) — a middle ground, rejected because it still requires per-tenant migration orchestration and connection/schema-switching complexity in the application, for isolation guarantees RetailPulse can achieve more cheaply with row-level scoping given its actual threat model (a single shared operator, not mutually adversarial tenants running arbitrary code).
- **No `tenant_id`, tenancy derived entirely through `branch_id` → `organization_id` joins** — rejected per Category 2's reasoning: indirect derivation makes every report and every isolation check a join away from correct, and makes sharding or per-tenant export materially harder later.

## Future direction

RetailPulse is designed to support thousands of tenants and millions of users on the shared-database architecture above, with horizontal scaling of the application tier, event-driven integrations ([ADR-005](./adr-005-domain-events.md)), and a plugin ecosystem ([ADR-009](./adr-009-plugin-system.md)) layered on top of — not instead of — this tenancy model. **Dedicated, isolated deployments remain a supported option for enterprise customers who require it** (a separate database or separate instance), but that is a deployment-topology decision layered on top of the same application and schema, not a different tenancy architecture (see [ADR-018](./adr-018-deployment.md)).

## Impact on future development

Every new table added from now on is classified against the four categories above before it is created. Every new subsystem (a new cache usage, a new queue, a new search index, a new broadcast channel) is designed from the start with "how does this stay tenant-scoped once Phase 28 lands" in mind, even while no enforcement exists yet — retrofitting a subsystem that assumed global state is exactly the expensive rework this ADR exists to avoid.

This document is the authoritative source for RetailPulse's SaaS multi-tenancy architecture. All future phases and AI-assisted development must comply with it.
