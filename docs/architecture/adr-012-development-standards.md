# ADR-012: Development Standards

Status: Accepted

Date: 2026-07-19

Related: [ADR-003 Backend Architecture](./adr-003-backend-architecture.md) · [ADR-005 Domain Events](./adr-005-domain-events.md) · [ADR-004 Frontend Architecture](./adr-004-frontend-architecture.md) · [ADR-013 Testing Strategy](./adr-013-testing-strategy.md)

Implementation-level coding conventions (PHP class shape, controller patterns, file-placement checklists, frontend page patterns) live in `.cursor/rules/*.mdc`, which implement this ADR. This document states the standards; the Cursor rules state exactly how to satisfy them in code.

---

## Why

RetailPulse is developed by a mix of human contributors and AI coding agents, iterating across 30 phases over a long timeline. Consistency of coding practice matters more here than in a short-lived project — a convention violated quietly in Phase 9 becomes a trap for whoever builds Phase 20 assuming the convention held. This ADR states the standards that apply across every module, independent of domain.

## What

Configuration over hardcoding, strict adherence to the layering in [ADR-003](./adr-003-backend-architecture.md), consistent naming/directory conventions, a deliberate (not ad hoc) refactoring policy, and a review process that checks new code against this ADR set rather than individual taste.

## How

### Configuration over hardcoding

If a value could plausibly differ per branch, per tenant (post-Phase-28), or per business — a tax rate, an approval threshold, a feature toggle, a provider choice — it belongs in `system_settings` (or the tenant/plan-tier configuration once Phase 23/28 land, see [ADR-009](./adr-009-plugin-system.md)), not as a PHP constant or an inline literal. The existing Phase 8 checkout settings groups (`tax`, `checkout`, `fbr`) are the template: nothing about tax mode, invoice numbering, or FBR behavior is hardcoded, all of it reads from a settings snapshot resolved at request time (`CheckoutConfigService`). Extend this pattern rather than special-casing a new hardcoded value "just for now" — "for now" hardcoded values are exactly what accumulates into an unmaintainable settings sprawl later.

### Events for cross-cutting side effects

Covered in full in [ADR-005](./adr-005-domain-events.md) — restated here because it is a development-time discipline as much as an architectural one: before writing a direct cross-module method call, check whether the relationship should instead be an event listener.

### Service layer and repository usage

Covered in full in [ADR-003](./adr-003-backend-architecture.md). The standard to hold every PR (human or AI-generated) to: does business logic live in a Service, does persistence live behind a Repository interface, is the Controller thin. A code review (or self-review before committing) that finds a query builder call, a business conditional, or a validation rule in a Controller should treat it as a defect to fix, not a style nit to let slide.

### Testing expectations

Covered in full in [ADR-013](./adr-013-testing-strategy.md) — a new business rule is not "done" without the Feature test coverage that ADR requires; this ADR does not restate the testing pyramid, it simply holds every change to it.

### Naming and namespace conventions

- PHP: `declare(strict_types=1);` at the top of new files (already universal in this codebase — events, services, observers all declare it), `final` classes by default unless a class is deliberately designed for extension.
- Follow the domain-first namespace convention from [ADR-002](./adr-002-modular-monolith.md) (`app/Services/{Domain}/`, `app/Events/{Domain}/`) for any new domain-specific class rather than adding another flat top-level class outside an established domain namespace.
- Match existing naming exactly when extending a convention (e.g. `Ensure{Domain}ModuleEnabled` for module-gate middleware, `{Entity}Policy` for policies, `{Entity}RepositoryInterface`/`{Entity}Repository` for repositories) — a new class that almost-but-not-quite matches an existing naming pattern is worse than one that clearly follows a different, equally documented pattern, because it reads as accidental inconsistency rather than an intentional choice.

### Directory structure

New backend code follows the structure already established under `app/` — `Services/{Domain}`, `Events/{Domain}`, `Listeners/{Domain}`, `Repositories/Contracts` + `Repositories/Eloquent`, `Http/Controllers/Admin/{Domain}`, `Http/Requests/Admin/{Domain}`, `Policies/`, `DTOs/`. New frontend code follows `resources/js/Pages/Admin/{Domain}/{Action}.jsx` mirroring the Inertia render call, with shared code placed per [ADR-004](./adr-004-frontend-architecture.md)'s component hierarchy. A new top-level directory under `app/` or `resources/js/` is a structural decision significant enough to be flagged and discussed, not something to introduce silently because a file "didn't seem to fit" one of the existing ones.

### Refactoring policy

- **Opportunistic drive-by refactoring of unrelated code is discouraged.** A bug fix or a feature addition touches the files it needs to touch; it does not also "clean up" a neighboring function's style or restructure a class it happens to open, because that inflates the diff, obscures the actual change being reviewed, and risks introducing regressions in code the task didn't require touching.
- **Deliberate refactoring is welcome and expected when a real pattern has emerged** — e.g. three near-duplicate implementations of the same concern is a signal to extract a shared abstraction, not a reason to write a fourth. This is scoped, intentional work, ideally called out as its own change rather than folded silently into an unrelated feature PR.
- **A refactor that changes a documented convention (a naming pattern, a layering rule, a component-hierarchy boundary) requires updating the relevant ADR in the same change** — code and documentation must not be allowed to drift; see this directory's [README](./README.md) for the ADR-change process.
- **Never refactor away a pattern you don't understand the reason for.** If an existing pattern looks unusual (e.g. a specific row-locking approach, a seemingly redundant validation), check the relevant ADR or phase doc for the rationale before "simplifying" it — several of RetailPulse's patterns exist because of a specific concurrency, tenancy, or compliance requirement that isn't visible from the code alone.

### Review process

- New code is checked, in order: (1) does it violate a stated ADR (layering, naming, security, tenancy); (2) does it have the test coverage [ADR-013](./adr-013-testing-strategy.md) requires; (3) does it match the nearest existing analogous pattern in the codebase; (4) is it documented per the requirements below.
- A reviewer (human or AI) citing "this violates ADR-00X" is a complete, specific reason to request changes — it does not need to be re-argued from first principles each time, because the ADR already carries its own Why/Trade-offs/Alternatives Considered.
- If a contributor believes an ADR itself is wrong for a specific case, the correct response is to propose an ADR amendment (per the architecture [README](./README.md)'s change process) — not to quietly ship code that contradicts it and let the inconsistency stand undocumented.
- `./vendor/bin/pint` (PHP) and the project's configured linter/formatter (frontend) are run before code is considered ready for review — style disagreements that a formatter would have caught are not a productive use of review time.

### Documentation requirements

- A new phase of meaningful scope gets a phase doc under `docs/phases/phase-NN-*.md` following the existing structure (Objective, Data Model, Services, API Endpoints, Acceptance Criteria) — this is the spec a reviewer and a future contributor both check against.
- A new architectural decision (new cross-cutting mechanism, new module boundary, new external dependency category) gets an ADR here, not just a mention in a PR description that will be unfindable in six months — see this directory's [README](./README.md) for the process.
- User-facing modules get a user manual entry (`docs/user-manual-*.md`) when they ship — these exist for HR/payroll, accounting/finance, customers/loyalty, and inventory/catalogue already, and are part of "done," not a follow-up task that quietly never happens.
- Known gaps between the current implementation and the intended design go in the relevant `gaps.md` (`docs/gaps/gaps.md`, `docs/phases/phase-12/gaps.md`) rather than being left undocumented — an undocumented gap gets silently treated as intended behavior by the next person who reads the code.

## Trade-offs

- **Discouraging drive-by refactors trades some opportunistic code-quality improvement for smaller, more reviewable diffs.** Accepted because a codebase this size accumulates more risk from large, hard-to-review diffs than it loses from a slightly-imperfect neighboring function staying as-is until its own deliberate refactor.
- **Requiring an ADR amendment before deviating from a documented pattern adds friction to a genuinely good new idea.** Accepted because the alternative — silent, undocumented deviation — is worse: it leaves the next contributor unable to tell whether the deviation was a deliberate improvement or an oversight.

## Alternatives considered

- **No formal standards document — rely on code review and osmosis** — rejected: this is exactly what produces the "convention violated quietly in Phase 9" failure mode this ADR opens with; a codebase built partly by AI agents across independent sessions cannot rely on tacit team knowledge the way a small, stable human team might.
- **Enforcing every standard here via automated static analysis / CI lint rules** — a good future direction (see below), not adopted as a full substitute now because several of these standards (refactoring judgment, "does this match the nearest existing pattern") are not mechanically checkable and still require review judgment informed by this document.

## Future direction

Standards that are mechanically checkable (naming patterns, layering violations like a query builder call inside a Controller, missing `declare(strict_types=1)`) are good candidates for codifying into static analysis/CI rules over time, freeing review judgment for the standards that genuinely require it (refactoring judgment, documentation completeness, matching the "spirit" of an existing pattern).

## Impact on future development

- A reviewer (human or AI) can check new code against this ADR plus [ADR-003](./adr-003-backend-architecture.md) and reject a PR that hardcodes a business-configurable value, skips a Feature test for a new business rule, or puts logic in the wrong layer — with a specific, citable reason rather than a vague "this doesn't feel right."
- Documentation lags code far less than it otherwise would, because "done" for a phase explicitly includes the phase doc / user manual / gap doc, not just working code.
- New contributors, including AI agents picking up a fresh session with no memory of prior conversations, can infer the expected pattern for a new piece of work by finding the nearest existing analog and matching its structure, naming, and test coverage.
