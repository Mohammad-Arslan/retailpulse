# ADR-015: Database Standards

Status: Accepted

Date: 2026-07-19

Related: [ADR-001 SaaS Multi-Tenancy](./adr-001-saas-multi-tenancy.md) · [ADR-011 Audit History](./adr-011-audit-history.md) · [ADR-014 Performance](./adr-014-performance.md) · [ADR-003 Backend Architecture](./adr-003-backend-architecture.md)

---

## Why

RetailPulse's database already spans 34+ migrations and 80+ tables across a dozen-plus domains, growing every phase. Schema inconsistency (one domain naming its foreign keys differently than another, one table forgetting the soft-delete convention another relies on) compounds into real bugs — a missing index that only shows up under production data volume, a hard delete that breaks a report six months later, a `tenant_id`-less table that becomes a Phase 28 migration headache ([ADR-001](./adr-001-saas-multi-tenancy.md)). This ADR is the schema-level counterpart to [ADR-003](./adr-003-backend-architecture.md)'s code-layer standards.

## What

Consistent naming, deliberate foreign-key and nullability policy, index discipline, a stated soft-delete/history default, and the `tenant_id` classification rules from [ADR-001](./adr-001-saas-multi-tenancy.md) applied at every migration.

## How

### Naming conventions

- Tables: `snake_case`, plural (`purchase_orders`, `leave_entitlements`), matching Laravel/Eloquent convention — no abbreviation-only names (`po_hdr`) where a clear full name fits.
- Columns: `snake_case`; foreign keys `{singular_table}_id` (`branch_id`, `employee_id`); boolean columns as an `is_`/`has_` prefixed predicate (`is_active`, `has_variants`) so their meaning reads unambiguously in a query.
- Pivot tables: alphabetical/singular pair of the two related tables (`branch_user`, matching Laravel convention), unless the pivot has its own identity/behavior per [ADR-001](./adr-001-saas-multi-tenancy.md)'s pivot-table guidance, in which case it gets a proper domain name instead (`branch_product_prices`, not `branch_product`).
- Enum-like status columns are backed string enums (`app/Enums/`) at the application layer, stored as their string value in the database, not raw integers a reader would need a lookup table to interpret.

### Migration standards

- One migration per logical schema change, named `{timestamp}_{imperative_description}` matching Laravel's convention (`create_x_table`, `add_y_to_x_table`, `extend_x_table`) — never a single migration silently bundling unrelated schema changes across domains.
- Migrations are additive/expand-first for anything touching a live table with data — a column rename is modeled as add-new-column → backfill → (later migration) drop-old-column, never a single non-additive rename that breaks any code deployed a moment earlier than the migration, consistent with [ADR-001](./adr-001-saas-multi-tenancy.md)'s note that shared-schema migrations apply to every tenant simultaneously.
- Migrations are not run automatically by an AI coding agent or CI step against a shared/production database — per existing repository convention, a human applies migrations deliberately.

### Foreign keys

Every foreign key is declared explicitly with `constrained()` and an explicit `nullOnDelete()` or `cascadeOnDelete()` matching the actual business relationship — never a bare integer column with no FK constraint "for simplicity." Choose the delete behavior deliberately:
- `cascadeOnDelete()` when the child record has no meaning without its parent (a `sale_items` row without its `sale`).
- `nullOnDelete()` when the child record should survive its parent's removal with the reference cleared (an optional `assigned_to` user reference).
- Restrict (the default, no cascade) when deleting the parent while children exist should be prevented outright and surfaced as an error, not silently resolved either way — appropriate for master data referenced by financial history.

### Nullable policy

A column is nullable only when "no value" is a genuinely valid business state — not as a default way to avoid deciding what a sensible required value or default is. A `tenant_id` column is the one deliberate, temporary exception (nullable pre-Phase-28 per [ADR-001](./adr-001-saas-multi-tenancy.md), because the column exists ahead of enforcement) — this is a documented, time-boxed exception, not a template for other columns.

### `tenant_id` policy

Every new migration is classified against [ADR-001](./adr-001-saas-multi-tenancy.md)'s four categories before creation. Category 2 (tenant-owned business data) tables get a direct, indexed, nullable-for-now `tenant_id` column. Category 1, 3, and 4 tables do not. This classification step is not optional or "add it later if we need it" — see [ADR-001](./adr-001-saas-multi-tenancy.md) for why retrofitting is expensive.

### Indexes

Covered in depth in [ADR-014](./adr-014-performance.md) — restated as a migration-time obligation: every foreign key, every frequently-filtered/sorted column, and every `tenant_id`/`branch_id` scoping column is indexed in the same migration that adds the column, not as a follow-up migration once a slow query is reported in production.

### Soft deletes and historical tables

Covered in depth in [ADR-011](./adr-011-audit-history.md) — restated as the concrete migration-level default: a new table for business/reporting-relevant data includes `deleted_at` (soft delete) unless it's genuinely transient/derivable (Category 3 infrastructure tables, per [ADR-001](./adr-001-saas-multi-tenancy.md)) or the model requires append-only immutability instead (e.g. `stock_movements`, which is insert-only — no updates or deletes at all, not even soft ones, because every row is a permanent ledger entry).

## Trade-offs

- **Explicit FK constraints with cascade/null/restrict decisions are more upfront design work per migration** than a bare unconstrained integer column — accepted because an unconstrained "foreign key" is a data-integrity time bomb (orphaned rows, referential garbage) that is far more expensive to clean up later than to declare correctly once.
- **Soft-delete-by-default means most tables never actually shrink** — accepted per [ADR-011](./adr-011-audit-history.md)'s reasoning; a hard-purge job under an explicit retention policy is the correct tool for genuinely reclaiming space, not silently switching a table to hard deletes.
- **Backed-string enums stored as strings cost slightly more storage/index size than integer codes** — accepted because a raw integer status column is a routine source of "what does `status = 3` mean" bugs across a codebase this large; the readability and correctness benefit outweighs the marginal storage cost.

## Alternatives considered

- **Integer/tinyint status codes instead of backed string enums** — rejected: cheaper storage, but every query, every debugging session, and every raw database inspection (support ticket investigation, backup restoration check) needs the application's enum mapping memorized or looked up; string enums are self-documenting in exactly the situations where documentation matters most (an incident, at 2am, reading raw rows).
- **Database-level `ON DELETE CASCADE` used universally "to keep it simple"** — rejected: several relationships in an ERP must never cascade-delete (deleting a customer should not silently cascade-delete their sales history) — deliberate per-relationship choice (above) is required, not a blanket default in either direction.
- **A single polymorphic "generic value store" table instead of per-domain typed tables** — rejected as a general pattern: it trades away the indexing, FK integrity, and query-planner efficiency of typed columns for a flexibility RetailPulse's schema doesn't actually need broadly; used narrowly and deliberately only where genuine polymorphism exists (e.g. a future Document Vault's polymorphic attachments, Phase 30), not as the default modeling approach.

## Future direction

As Phase 28 lands, the `tenant_id` nullable-for-now exception above is retired for new tables — `tenant_id` becomes a required, non-nullable column on every new Category 2 table from that point forward, and a scheduled backfill migration makes existing tables non-nullable too. Database standards here are expected to hold unchanged through that transition; only the nullability exception expires.

## Impact on future development

- A new migration's author can determine every required decision (naming, nullability, FK behavior, tenant classification, soft-delete-or-not) by checking this ADR, without guessing from inconsistent precedent elsewhere in the schema.
- Schema review (human or AI) can reject a migration that skips an index on an FK column, uses a bare unconstrained integer for a relationship, or omits `tenant_id` classification — citing this ADR specifically.
- The Phase 28 tenancy migration is additive (making existing nullable columns required) rather than a scramble to first discover which tables even need the column, because that classification already happened at each table's creation time.
