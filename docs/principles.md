# RetailPulse Engineering Principles

These are the non-negotiable principles behind every ADR in [`architecture/`](./architecture/README.md). Where an ADR states a specific rule, one of these principles is almost always the reason. When you're unsure how to handle a case an ADR doesn't cover explicitly, these principles are the fallback reasoning — not personal preference, not "how I'd normally do it in another codebase."

## Configuration over hardcoding

If a value could plausibly differ per branch, per tenant, or per business — a tax rate, an approval threshold, a feature toggle, a provider choice — it is configuration, not code. See [ADR-012](./architecture/adr-012-development-standards.md), [ADR-009](./architecture/adr-009-plugin-system.md).

## Convention over configuration, where appropriate

Configuration is for things a business legitimately needs to vary. Internal structure — file placement, naming, layering — is convention: fixed, consistent, and not something every module reinvents. Don't confuse "should this be configurable" (business-facing) with "should this be conventional" (developer-facing); the two questions have different answers. See [ADR-002](./architecture/adr-002-modular-monolith.md), [ADR-003](./architecture/adr-003-backend-architecture.md).

## Event-driven architecture

Cross-module side effects are expressed as domain events, not direct cross-module calls — a module dispatches a fact about what happened and does not know or care who reacts to it. See [ADR-005](./architecture/adr-005-domain-events.md).

## API-first

Anything meant to be used by an external party is designed as a deliberate, documented, versioned API surface — never a side effect of "the internal routes happened to be reachable." See [ADR-008](./architecture/adr-008-public-api.md).

## Security by default

New features default to the more secure posture: disabled until configured, authenticated until proven public, tenant-scoped once tenancy exists. Security is not a hardening pass applied before launch — it's the starting assumption for every new line of code. See [ADR-010](./architecture/adr-010-security.md).

## Audit everything

Every mutation to a security- or business-sensitive model is captured automatically, universally, without needing a feature-specific decision to "remember to audit this one." See [ADR-011](./architecture/adr-011-audit-history.md).

## Historical data is immutable

Once a financial or HR record is finalized, it is never edited in place. A correction is a new, linked reversal or adjustment record. This is true even when it would be more convenient to just fix the number. See [ADR-011](./architecture/adr-011-audit-history.md).

## Business rules belong in Services

Validation belongs in FormRequests. Persistence belongs in Repositories. Business rules — the actual "is this allowed, what happens next" logic — belong in exactly one place: the Service. See [ADR-003](./architecture/adr-003-backend-architecture.md).

## Thin controllers

A Controller authenticates the request, calls a Service, and returns a response. It never builds a query, never contains a business conditional, never silently swallows a domain exception. See [ADR-003](./architecture/adr-003-backend-architecture.md).

## Reusable UI

A second page needing a component 90% similar to an existing one extracts the shared 90%, rather than forking it. The component hierarchy (`ui/` → `common/` → `admin/`) exists so reuse has an obvious home at every level of genericness. See [ADR-004](./architecture/adr-004-frontend-architecture.md).

## Internationalization

Every user-facing string is a translation key from the first version of a component, not retrofitted later. See [ADR-004](./architecture/adr-004-frontend-architecture.md).

## Accessibility

Every new page is expected to meet WCAG 2.1 AA, backed by Radix's accessible primitives — this is part of a page being complete, not a follow-up audit item. See [ADR-004](./architecture/adr-004-frontend-architecture.md).

## Performance

Query efficiency (no N+1, proper indexes), a clear caching/queueing strategy, and awareness of the shared-tenancy blast radius of a slow query are reviewed the same way authorization is — before merge, not after a slow-query report from production. See [ADR-014](./architecture/adr-014-performance.md).

## Scalability

Architecture decisions (modular monolith, shared-schema tenancy, queue-based async work) are made to scale to thousands of tenants and millions of users without a rearchitecture — not because RetailPulse is at that scale today, but because retrofitting scale into a system not designed for it is far more expensive than designing for it from the start. See [ADR-001](./architecture/adr-001-saas-multi-tenancy.md), [ADR-002](./architecture/adr-002-modular-monolith.md).

## Multi-tenancy

Tenant data isolation is a security boundary, prepared for continuously from early development even while enforcement is deferred to its scheduled phase. See [ADR-001](./architecture/adr-001-saas-multi-tenancy.md).

## Extensibility

New capabilities — first-party or, eventually, third-party — are built against a small, well-understood set of extension points (domain events, webhooks, workflow definitions, navigation registry, module registration), not by reaching around module boundaries. See [ADR-009](./architecture/adr-009-plugin-system.md).

## Backward compatibility

A published API contract, a database migration on a live shared schema, and an event payload are all things other code depends on — none of them change in a breaking way without a deliberate versioning or migration strategy. See [ADR-008](./architecture/adr-008-public-api.md), [ADR-015](./architecture/adr-015-database-standards.md).

## Developer experience

Consistency (naming, layering, directory structure) is what lets a contributor — human or AI, on their first day in a given module — predict where something lives and how it's built, without re-deriving the codebase's conventions from scratch every time. See [ADR-012](./architecture/adr-012-development-standards.md).

## AI-friendly architecture

Documentation, naming, and layering are kept explicit and discoverable specifically because AI coding agents are expected to build a meaningful share of RetailPulse's code — a convention that only lives in a senior engineer's head doesn't work when the contributor reading the codebase for the first time is a fresh AI session with no memory of prior conversations. See `CLAUDE.md` and [architecture/README.md](./architecture/README.md)'s AI Governance section.

---

These principles are not independently negotiable per feature. If a specific case seems to call for violating one, that's a signal to either find the ADR that already resolves the tension, or to raise a new ADR — not to make a silent one-off exception.
