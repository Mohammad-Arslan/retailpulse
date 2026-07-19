# ADR-014: Performance

Status: Accepted

Date: 2026-07-19

Related: [ADR-003 Backend Architecture](./adr-003-backend-architecture.md) · [ADR-015 Database Standards](./adr-015-database-standards.md) · [ADR-001 SaaS Multi-Tenancy](./adr-001-saas-multi-tenancy.md) · [ADR-018 Deployment](./adr-018-deployment.md)

---

## Why

RetailPulse's shared-database, shared-schema tenancy model ([ADR-001](./adr-001-saas-multi-tenancy.md)) means every tenant's performance depends on every other tenant's query discipline — there is no physical isolation to absorb a bad query. Combined with the reporting-heavy nature of an ERP (dashboards, aging reports, payroll runs, data marts) sharing infrastructure with latency-sensitive request paths (POS checkout, barcode scan lookups), performance is not an optimization pass reserved for a "hardening phase" — it is a correctness property of the shared platform from the first query written.

## What

Query efficiency (no N+1, indexed access paths), a clear cache/queue/real-time strategy per kind of workload, and explicit performance-sensitive code paths (invoice sequencing, bulk import) that are held to a higher bar than ordinary CRUD.

## How

### N+1 prevention

Eloquent relationships accessed in a loop (a list page rendering `$product->category->name` for every row) are eager-loaded (`with()`/`load()`) at the point the collection is fetched — in the Repository or Service, not patched reactively in the Controller after a slow page is noticed. A new index/list endpoint returning a collection with related models is reviewed for N+1 risk the same way it's reviewed for [ADR-003](./adr-003-backend-architecture.md)'s layering — it is a correctness-adjacent property of the query, not a separate optional pass.

### Indexes

Every foreign key column, every column used in a `WHERE`/`ORDER BY` on a table expected to grow past a few thousand rows, and every `tenant_id`/`branch_id` scoping column ([ADR-001](./adr-001-saas-multi-tenancy.md), [ADR-015](./adr-015-database-standards.md)) gets an index at migration time — not added reactively after a slow-query report. Composite indexes are ordered with the most selective/most-commonly-filtered column first, matching the actual query shape the Repository issues (see [ADR-015](./adr-015-database-standards.md) for the full migration-level convention).

### Caching

RetailPulse's default cache driver is the database driver (`config/cache.php`), consistent with the "no extra infrastructure required to run the app" principle for development and small deployments; production deployments are expected to configure a Redis/Memcached driver for cache and session storage as load grows ([ADR-018](./adr-018-deployment.md)) — the choice of driver is operational configuration, not something application code should assume or hardcode. Cache keys that are tenant/branch-scoped (post-Phase-28) must include the tenant/branch identifier in the key — a shared cache key across tenants is a data-leak risk, not just a correctness bug ([ADR-010](./adr-010-security.md)). Cache invalidation is explicit (on the write path that changes the cached data), not time-only TTL expiry for data where staleness would be user-visibly wrong (e.g. current stock level) — TTL-only caching is reserved for data where brief staleness is acceptable (e.g. a dashboard KPI refreshed every few minutes).

### Queues

Laravel's queue system (database driver by default, `config/queue.php`) is the mechanism for anything that shouldn't block a request/response cycle: domain event listeners doing real work ([ADR-005](./adr-005-domain-events.md)), inbound webhook processing ([ADR-007](./adr-007-integration-hub.md)), bulk import/export row processing, scheduled jobs (workflow SLA timeouts per [ADR-006](./adr-006-workflow-engine.md), usage metering per Phase 28). A request handler that does meaningful I/O-bound work synchronously "because it's simpler" is a performance defect once that path is under real load — if a job can queue, it queues.

### Lazy loading (frontend)

Covered fully in [ADR-004](./adr-004-frontend-architecture.md) — restated here because it's a performance decision as much as a frontend-architecture one: large, rarely-used bundles are lazy-loaded; this is not applied reflexively to every component.

### Database optimization for reporting workloads

Operational (OLTP) queries and analytical (reporting/BI) queries have different performance profiles and are not optimized identically — a report that aggregates across millions of historical sale rows is not run against the same indexes/query patterns tuned for "fetch this one sale by ID." [ADR-016](./adr-016-reporting-bi.md)'s data-mart approach exists specifically so heavy analytical queries don't compete with or degrade transactional request latency.

### Scaling

The modular monolith ([ADR-002](./adr-002-modular-monolith.md)) scales horizontally at the application tier (more PHP-FPM/queue-worker processes behind a load balancer) against one database, which is the near-term scaling story. Vertical database scaling and read-replica strategies (routing reporting queries to a replica, keeping OLTP writes on the primary) are the next lever once a single database instance becomes the bottleneck — this is an [ADR-018](./adr-018-deployment.md) deployment-topology concern, not an application-code concern; application code should not assume it is always talking to a single database connection in a way that would make introducing a read replica later a rewrite.

## Trade-offs

- **Eager loading everywhere would waste memory/bandwidth fetching relationships a given request doesn't use** — the rule above is "eager-load what you actually iterate," not "eager-load every relationship defensively." Under-loading (N+1) and over-loading (fetching unused relations) are both defects; the fix is matching the load to the actual access pattern, which requires the developer to know what the view/response actually needs.
- **Explicit cache invalidation is more code than TTL-only expiry** — accepted for data where staleness is user-visibly wrong (stock levels, account balances); TTL-only remains fine, and cheaper to write, for genuinely tolerant data (a dashboard KPI).
- **Queueing everything non-trivial adds operational surface** (a queue worker that must be running, failure/retry handling, monitoring for stuck jobs) — accepted because the alternative (slow synchronous request handlers under load) directly degrades the shared platform's latency for every tenant sharing it.

## Alternatives considered

- **Full-page/response caching (cache the whole HTTP response) as the default performance strategy** — rejected as the default: RetailPulse's pages are largely per-user, per-branch, and increasingly per-tenant, which makes whole-response caching either ineffective (cache key explosion) or actively dangerous (serving one tenant's cached response to another). Targeted data-level caching (above) is preferred; response caching remains a valid, narrow tool for genuinely public, identical-for-everyone content (none currently identified).
- **A dedicated read-replica strategy from day one** — rejected as premature: no current workload has demonstrated the primary database is a bottleneck; introducing replica routing before it's needed adds consistency-lag complexity (read-after-write correctness) for a problem that doesn't exist yet. Revisit when [ADR-018](./adr-018-deployment.md)'s deployment scale makes it concrete.
- **Denormalizing aggressively into the OLTP schema to speed up reports** — rejected as the general reporting strategy: it couples transactional schema evolution to reporting query needs and risks the exact double-source-of-truth problem [ADR-016](./adr-016-reporting-bi.md)'s data mart is designed to avoid by keeping the two concerns separate.

## Future direction

As tenant count and data volume grow post-Phase-28, expect: read-replica routing for reporting queries, a move from database-driver cache/queue to Redis in every production deployment (not just recommended), and formalized performance regression benchmarks for the small set of identified high-risk paths (invoice sequencing, bulk import, dashboard aggregate queries) rather than ad hoc profiling only when something is already slow in production.

## Impact on future development

- A new list/report endpoint is reviewed for N+1 queries and missing indexes before merge, the same way it's reviewed for authorization — this is not an optional pass deferred to "later if it's slow."
- Anything that does meaningful I/O outside the immediate point of the request (notification, external call, bulk processing) is queued by default, not a judgment call made under time pressure.
- Reporting-heavy features are designed against the data mart ([ADR-016](./adr-016-reporting-bi.md)) from the start, not against live OLTP tables with a plan to "optimize later."
