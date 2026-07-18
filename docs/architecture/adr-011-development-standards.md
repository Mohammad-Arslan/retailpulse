# ADR-011: Development Standards

Status: Accepted

Date: 2026-07-19

Related: [ADR-004 Layered Architecture](./adr-004-layered-architecture.md) · [ADR-003 Domain Events](./adr-003-domain-events.md) · [ADR-012 Frontend Architecture](./adr-012-frontend-architecture.md)

---

# Context

RetailPulse is developed by a mix of human contributors and AI coding agents, iterating across 30 phases over a long timeline. Consistency of coding practice matters more here than in a short-lived project — a convention violated quietly in Phase 9 becomes a trap for whoever builds Phase 20 assuming the convention held. This ADR states the standards that apply across every module, independent of domain.

# Decision

## Configuration over hardcoding

If a value could plausibly differ per branch, per tenant (post-Phase-28), or per business — a tax rate, an approval threshold, a feature toggle, a provider choice — it belongs in `system_settings` (or the tenant/plan-tier configuration once Phase 23/28 land, see [ADR-009](./adr-009-plugin-architecture.md)), not as a PHP constant or an inline literal. The existing Phase 8 checkout settings groups (`tax`, `checkout`, `fbr`) are the template: nothing about tax mode, invoice numbering, or FBR behavior is hardcoded, all of it reads from a settings snapshot resolved at request time (`CheckoutConfigService`). Extend this pattern rather than special-casing a new hardcoded value "just for now" — "for now" hardcoded values are exactly what accumulates into an unmaintainable settings sprawl later.

## Events for cross-cutting side effects

Covered in full in [ADR-003](./adr-003-domain-events.md) — restated here because it is a development-time discipline as much as an architectural one: before writing a direct cross-module method call, check whether the relationship should instead be an event listener.

## Service layer and repository usage

Covered in full in [ADR-004](./adr-004-layered-architecture.md). The standard to hold every PR (human or AI-generated) to: does business logic live in a Service, does persistence live behind a Repository interface, is the Controller thin. A code review (or self-review before committing) that finds a query builder call, a business conditional, or a validation rule in a Controller should treat it as a defect to fix, not a style nit to let slide.

## Testing expectations

- **Every Service method with a business rule gets a Feature test** exercising the rule's boundary conditions (e.g. `tests/Feature/Phase12/Phase12Wave2LeaveAccrualTest.php` for leave accrual edge cases) — not just the happy path through the controller.
- **Feature tests over heavily-mocked unit tests for business logic** — `tests/Feature/` is the dominant test type in this codebase (Accounting, Admin, Auth, Checkout, Loyalty, Pos, Procurement, Phase12) precisely because business rules here mostly involve real database state (branch scoping, RBAC, multi-row calculations) that a mocked unit test would not catch drifting.
- **Unit tests (`tests/Unit/`) are for logic genuinely isolable from the database/framework** — a pure calculation, a value object, a role-resolution algorithm (`RoleServiceTest`).
- **Shared test setup goes in `tests/Concerns/`** (`SeedsRbac`, `SeedsAccounting`, `SeedsLoyaltyEngine`) as traits, not copy-pasted per test file — if a third test needs the same seed data as two existing tests, extract a concern rather than duplicating the setup a third time.
- A migration or model change that affects an existing audited/financial workflow ([ADR-005](./adr-005-audit-trail.md)) should not be considered complete until the existing feature tests for that domain still pass — these tests are the executable specification of the business rule, not incidental scaffolding.
- Run `composer test` / `php artisan test` before considering backend work done; run `./vendor/bin/pint` before considering it committed (Laravel Pint is the single source of truth for PHP formatting — do not hand-format against a different style).

## Documentation requirements

- A new phase of meaningful scope gets a phase doc under `docs/phases/phase-NN-*.md` following the existing structure (Objective, Data Model, Services, API Endpoints, Acceptance Criteria) — this is the spec a reviewer and a future contributor both check against.
- A new architectural decision (new cross-cutting mechanism, new module boundary, new external dependency category) gets an ADR here, not just a mention in a PR description that will be unfindable in six months — see this directory's [README](./README.md) for the process.
- User-facing modules get a user manual entry (`docs/user-manual-*.md`) when they ship — these exist for HR/payroll, accounting/finance, customers/loyalty, and inventory/catalogue already, and are part of "done," not a follow-up task that quietly never happens.
- Known gaps between the current implementation and the intended design go in the relevant `gaps.md` (`docs/gaps/gaps.md`, `docs/phases/phase-12/gaps.md`) rather than being left undocumented — an undocumented gap gets silently treated as intended behavior by the next person who reads the code.

## Naming and namespace conventions

- PHP: `declare(strict_types=1);` at the top of new files (already universal in this codebase — events, services, observers all declare it), `final` classes by default unless a class is deliberately designed for extension.
- Follow the domain-first namespace convention from [ADR-002](./adr-002-modular-architecture.md) (`app/Services/{Domain}/`, `app/Events/{Domain}/`) for any new domain-specific class rather than adding another flat top-level class outside an established domain namespace.
- Match existing naming exactly when extending a convention (e.g. `Ensure{Domain}ModuleEnabled` for module-gate middleware, `{Entity}Policy` for policies, `{Entity}RepositoryInterface`/`{Entity}Repository` for repositories) — a new class that almost-but-not-quite matches an existing naming pattern is worse than one that clearly follows a different, equally documented pattern, because it reads as accidental inconsistency rather than an intentional choice.

# Consequences

- A reviewer (human or AI) can check new code against this ADR plus [ADR-004](./adr-004-layered-architecture.md) and reject a PR that hardcodes a business-configurable value, skips a Feature test for a new business rule, or puts logic in the wrong layer — with a specific, citable reason rather than a vague "this doesn't feel right."
- Documentation lags code far less than it otherwise would, because "done" for a phase explicitly includes the phase doc / user manual / gap doc, not just working code.
- New contributors, including AI agents picking up a fresh session with no memory of prior conversations, can infer the expected pattern for a new piece of work by finding the nearest existing analog and matching its structure, naming, and test coverage.
