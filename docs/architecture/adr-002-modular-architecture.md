# ADR-002: Modular Monolith Architecture

Status: Accepted

Date: 2026-07-19

Related: [ADR-004 Layered Architecture](./adr-004-layered-architecture.md) · [ADR-009 Plugin Architecture](./adr-009-plugin-architecture.md) · [ADR-001 SaaS Multi-Tenancy](./adr-001-saas-multi-tenancy.md)

---

# Context

RetailPulse's SRS (`docs/srs.md` v4.0) spans 30+ business domains — POS, inventory, accounting, HR/payroll, procurement, loyalty, e-commerce integration, restaurant operations, and more (`docs/phases/`). A naive single-namespace Laravel app would collapse under its own weight long before Phase 30 ships: controllers, services, and models for unrelated domains would tangle, and no team could safely own a slice of the codebase.

The alternative extremes are both wrong for RetailPulse's stage:

- **Microservices** — network overhead, distributed transactions across what are fundamentally one business's tightly-coupled domains (a sale touches inventory, accounting, and loyalty in the same request), and an operations burden far beyond what a small team can support.
- **Unstructured monolith** — no enforced boundaries; every module becomes able to reach into every other module's tables and internals, and the codebase becomes unrefactorable.

# Decision

RetailPulse is a **modular monolith**: one deployable Laravel application, internally organized into domain modules with enforced boundaries, deployed as a single unit, sharing one database (per [ADR-001](./adr-001-saas-multi-tenancy.md)'s shared-schema strategy).

This gives most of microservices' organizational clarity (clear ownership, bounded contexts, replaceable internals) without the operational cost, while keeping the door open to extracting a genuinely hot module (e.g. reporting/BI, per Phase 27) into a separate service later if it ever needs independent scaling — a modular monolith is the correct precursor to that, an unstructured one is not.

## Module boundaries today

RetailPulse does not (yet) use a package-per-module structure (no `Modules/` directory, no `nwidart/laravel-modules`). Module boundaries are currently enforced **by namespace convention within `app/`**, mirroring the SRS domain list:

```
app/Services/{Domain}/       e.g. Accounting, Hr, Leave, Overtime, Payroll, Expense,
                              Procurement, Checkout, Pos, Loyalty, Customer,
                              ImportExport, Navigation, Search, Dashboard, AI, HelpSupport
app/Events/{Domain}/
app/Listeners/{Domain}/
```

Flat, single-class services (`BranchService`, `ProductService`, `InventoryService`, etc.) belong to foundational/shared domains that predate the sub-namespace convention; new domain-specific work should follow the `{Domain}/` sub-namespace pattern shown above rather than adding more flat top-level services, so module boundaries stay legible as the domain count grows.

A module, in this codebase, is the vertical slice of:

- Migrations for that domain's tables
- Eloquent models for that domain
- Its repository contracts + Eloquent implementations (`app/Repositories/Contracts`, `app/Repositories/Eloquent`)
- Its services (`app/Services/{Domain}`)
- Its policies (`app/Policies`)
- Its controllers and form requests (`app/Http/Controllers/Admin/{Domain}`, `app/Http/Requests/Admin/{Domain}`)
- Its events/listeners (`app/Events/{Domain}`, `app/Listeners/{Domain}`)
- Its frontend pages (`resources/js/Pages/Admin/{Domain}`)
- Its feature tests (`tests/Feature/{Domain}`)

## Module ownership

Each domain module in the index below is expected to have a single logical owner (a person or pair, not necessarily exclusive) responsible for the invariants of its tables and services. Ownership is documented informally via the phase docs (`docs/phases/phase-NN-*.md`) that introduced the module — consult the phase doc before making a cross-cutting change to a domain you don't normally work in.

| Domain | Primary phase | Notes |
| :--- | :--- | :--- |
| Identity & RBAC | Phase 1 | Foundational — every other module depends on it |
| Multi-Branch | Phase 3 | `BranchContext` is a foundational cross-cutting concern, see below |
| Catalog / PIM | Phase 4 | |
| Inventory & Warehouse | Phase 5 | |
| POS & Checkout | Phase 7, 8, 17 | |
| Customers & Loyalty | Phase 9 | |
| Procurement | Phase 10 | |
| Accounting & Finance | Phase 11 | |
| HR & Payroll | Phase 12 | Largest module by SRS section count; see `docs/phases/phase-12/` |
| Reporting & Analytics | Phase 13, 27 | |
| Notifications / Returns / Tax | Phase 14 | |
| API & Integrations | Phase 15 | See [ADR-007](./adr-007-integration-hub.md), [ADR-008](./adr-008-public-api.md) |
| Module Config Engine | Phase 23 | See [ADR-009](./adr-009-plugin-architecture.md) |
| SaaS Multi-Tenancy | Phase 28 | See [ADR-001](./adr-001-saas-multi-tenancy.md) |
| Workflow Engine | Phase 29 | See [ADR-006](./adr-006-workflow-engine.md) |

## Cross-module communication rules

1. **A module may depend "downward" on foundational modules** (Identity/RBAC, Multi-Branch, Catalog) directly — these are load-bearing for the whole app and are not treated as peer modules.
2. **A module must not reach into another peer module's Eloquent models directly from its own service layer to mutate state it doesn't own.** Read access via the owning module's repository/service is fine (e.g. Checkout reading `Product`/`Inventory`); *writing* another module's data or bypassing its service invariants is not. If Checkout needs Inventory to move, it calls `InventoryService`, it doesn't hand-roll a stock mutation.
3. **Cross-module side effects that are not the direct point of the request are expressed as domain events**, not direct method calls — see [ADR-003](./adr-003-domain-events.md). Example: `SaleCompleted` triggers loyalty point accrual (`ProcessLoyaltyOnSaleCompleted`) and will trigger accounting posting — Checkout does not import and call the Loyalty or Accounting service directly.
4. **`BranchContext`** (`app/Support/BranchContext.php`) and, once Phase 28 lands, `TenantContext`, are the two sanctioned pieces of ambient, cross-cutting request state. Do not invent a third global request-scoped singleton — extend one of these two, or pass explicit parameters.
5. **Shared, domain-agnostic infrastructure** (Navigation, Search, Dashboard, ImportExport, AuditService) is intentionally exempt from the "modules don't reach into each other" rule in the other direction: any module may register with them (e.g. `AdminNavigationCatalog`, audit registration in `AppServiceProvider`), because that's their job.

# Consequences

- New domains are added by creating a new `{Domain}` sub-namespace following the pattern above, not by growing an unrelated module's files.
- A module can be refactored internally (swap a repository implementation, restructure its services) without other modules noticing, as long as its public surface (its Service class methods, its events) stays stable.
- If a module ever needs independent scaling or a separate release cadence, the sub-namespace boundary is what makes extraction to a separate service tractable — this is why the boundary is enforced now, before it's forced by a production incident.
