# ADR-003: Backend Architecture

Status: Accepted

Date: 2026-07-19

Related: [ADR-002 Modular Monolith](./adr-002-modular-monolith.md) · [ADR-012 Development Standards](./adr-012-development-standards.md) · [ADR-010 Security](./adr-010-security.md) · [ADR-005 Domain Events](./adr-005-domain-events.md)

Implementation-level detail (file placement, PHP conventions, per-layer code checklists) lives in `.ai/rules/backend.mdc`, which implements this ADR — read that rule for the concrete "how to write it" once this ADR's boundaries are understood.

---

## Why

Laravel does not enforce a layering discipline out of the box — it is entirely possible to put validation, business logic, and query building all inside a controller method. That works for a small app; it does not survive an ERP with 68+ services, 60+ policies, and modules as complex as HR/Payroll (`docs/phases/phase-12/`). Without an enforced layering convention, business rules end up duplicated between the admin controller and the API controller for the same entity, validation logic drifts from what the database actually allows, and testing requires spinning up the full HTTP stack because logic isn't isolated anywhere else.

## What

Every admin/API write path follows this layering, in this order:

```
Route → Middleware → Controller → FormRequest → Service → Repository → Model
                                        ↓
                                      DTO (passed between Service and Repository/other services)
```

## How

### Responsibility per layer

**Route** (`routes/admin.php`, `routes/api.php`) — binds a URI + HTTP verb to a controller action, attaches route-level middleware (`auth`, `EnsureAdminAccess`, `SetBranchContext`, module-gate middleware like `EnsureHrModuleEnabled`), and names the route. No logic.

**Middleware** — cross-cutting concerns that apply before the controller runs: authentication, branch context resolution (`SetBranchContext`), module-enabled gates (`EnsureHrModuleEnabled`, `EnsureAccountingModuleEnabled`), locale (`SetLocale`). Middleware does not contain business logic specific to one feature.

**Controller** (`app/Http/Controllers/Admin/{Domain}/`, `app/Http/Controllers/Api/V1/{Domain}/`) — thin. A controller action:
1. Type-hints a `FormRequest` (validation already ran by the time the method body executes).
2. Calls exactly one service method (occasionally orchestrates 2–3 for a single use case, e.g. create-then-redirect), passing DTOs/primitives, not raw `Request` objects, into the service.
3. Returns an `Inertia::render(...)` response (admin) or a JSON resource/response (API).

A controller must never: build an Eloquent query itself, contain a validation rule, contain a business rule ("if this employee's grade allows...", "if this sale total exceeds..."), or catch a domain exception to paper over it silently.

**FormRequest** (`app/Http/Requests/Admin/{Domain}/`, `app/Http/Requests/Api/...`) — owns **input validation and request-level authorization**. `authorize()` typically delegates to a Policy (see [ADR-010](./adr-010-security.md)); `rules()` defines shape/type/format validation. A FormRequest validates that the *request is well-formed and the actor may submit it* — it does not validate business invariants that require loading other records or cross-entity state (e.g. "this leave balance is sufficient" belongs in the Service, because it requires the entitlement record, not just the request payload).

**Service** (`app/Services/{Domain}/`) — owns **business logic**: orchestration across repositories, business-rule enforcement, transaction boundaries (`DB::transaction`), dispatching domain events ([ADR-005](./adr-005-domain-events.md)), and translating between DTOs and repository calls. This is where "is this leave balance sufficient," "does this discount exceed the manager-approval threshold," and "recompute the invoice sequence under a row lock" live. A service method is the natural seam for a feature test — it should be callable and testable without an HTTP request.

**Repository** (`app/Repositories/Contracts/{X}RepositoryInterface.php`, `app/Repositories/Eloquent/{X}Repository.php`) — owns **persistence and query construction**. Services depend on the interface (bound in `AppServiceProvider`), never the Eloquent implementation directly, so persistence can be swapped or mocked in tests without touching service logic. A repository method returns models/collections/DTOs; it does not contain business rules about *whether* an operation should happen, only *how* to fetch/persist.

**DTO** (`app/DTOs/`) — typed data carried between layers (Service ↔ Repository, Service ↔ Service) so that neither layer needs to know the shape of a `Request` or an Eloquent model to do its job. Prefer a DTO over passing an associative array across a layer boundary — arrays lose type safety and IDE/static-analysis support that a `readonly` DTO class gives you.

**Model** (`app/Models/`) — Eloquent relationships, casts, scopes, and accessors that describe *what the data is*, not what should happen to it. Model observers (`AuditObserver`) are the one sanctioned exception to "models don't contain business logic" — audit logging is a universal, cross-cutting concern by design (see [ADR-011](./adr-011-audit-history.md)), not a feature-specific rule.

### Validation responsibility, precisely

| Validation type | Owner |
| :--- | :--- |
| Field required / type / format / max length | FormRequest `rules()` |
| Uniqueness / exists-in-database checks scoped to the request | FormRequest `rules()` (`Rule::unique`, `Rule::exists`) |
| "May this actor perform this action at all" | FormRequest `authorize()` → Policy |
| Cross-entity business invariants (balance sufficiency, threshold checks, state-machine legality) | Service |
| Data-integrity constraints only the database can guarantee (FK, unique index) | Migration/schema, as a backstop — never the *only* layer enforcing a rule the user should get a friendly error for |

### Dependency injection

Services and repositories are resolved through Laravel's container via constructor injection — `private readonly` promoted properties, never `app()`/`resolve()` calls scattered inside method bodies to fetch a collaborator on demand. Repository interfaces are bound to their Eloquent implementations in `AppServiceProvider::register()` (already 40+ bindings following the identical `$this->app->bind(XRepositoryInterface::class, XRepository::class)` shape) — a new repository follows that exact pattern, not a special-cased binding elsewhere. This is what makes a service swappable/mockable in a Feature test without touching its constructor signature.

### Transactions

A Service method that writes to more than one table, or that must not leave partial state if a later step fails, wraps its writes in `DB::transaction(...)`. The transaction boundary lives in the Service, never in the Controller or Repository — the Service is the layer that knows the full unit of work (e.g. `InvoiceNumberService`'s `FOR UPDATE` row lock inside a transaction for race-safe sequence generation is Service-owned logic, not something the Controller orchestrates by calling the Repository twice). Domain events ([ADR-005](./adr-005-domain-events.md)) that must only fire on successful commit are dispatched after the transaction closes, or via `DB::afterCommit()` when the dispatch happens from inside the transaction closure — a listener must never observe a change that then rolls back.

### Error handling

Laravel 13's exception handling is configured centrally via `bootstrap/app.php`'s `->withExceptions(...)` closure — there is no per-controller `try/catch`-and-render pattern. Domain-specific failures a Service wants the Controller/frontend to react to distinctly (insufficient balance, invalid state transition, threshold exceeded) are raised as typed exceptions the global handler renders consistently (a validation-style error back to Inertia, or a structured JSON error for the API), not caught and silently converted to a generic redirect inside the Controller. A Controller catching a domain exception just to swallow it is a defect against this ADR — if a Controller needs to react to a specific failure (e.g. show a specific flash message), it catches the specific typed exception and re-raises or maps it deliberately, it does not blanket-catch `\Throwable`.

## Trade-offs

- **More files per feature** than a "fat controller" approach — a single CRUD resource touches a Controller, a FormRequest (or two), a Service, a Repository interface + implementation, and often a DTO. Accepted because the alternative (logic duplicated across admin and API controllers for the same entity) has already been observed to cost more across a codebase this size than the extra files cost to navigate.
- **Repository interfaces add indirection** for entities that will realistically never have a second persistence implementation. Accepted uniformly anyway, because the primary value is testability (mocking the interface in a Feature/Unit test) and consistency (a contributor never has to guess "does this domain use a repository or not"), not hypothetical swappability of the database engine.
- **Strict layering slows down trivial changes** (a one-line copy fix does not need to touch five files) — this ADR governs business-logic-bearing changes; a purely cosmetic or copy change is not required to route through every layer it doesn't touch.

## Alternatives considered

- **Active Record-only (fat models, "skinny controller, fat model")** — rejected: it was tried implicitly in early Laravel-idiomatic code and pushed business logic into Eloquent models, which then couldn't be tested without a database and coupled persistence to business rules RetailPulse needs to keep independent (e.g. changing how inventory is queried should never risk changing what counts as a valid stock adjustment).
- **CQRS with separate read/write models** — considered for high-traffic reporting paths, rejected as the default for the whole application: the complexity of maintaining separate write and read models is not justified for most of RetailPulse's CRUD-shaped domains; where a genuine read-heavy reporting need exists, [ADR-016](./adr-016-reporting-bi.md)'s data-mart approach is the targeted answer instead of restructuring every module's write path.
- **Action classes (single-responsibility invokable "Action" objects) instead of Services** — a reasonable alternative pattern used by some Laravel codebases; not adopted because Services already provide the same single-entry-point testability with less proliferation of one-method classes per operation, and switching now would mean maintaining two competing patterns during a long migration for no behavioral gain.

## Future direction

As the domain count grows (Phase 19–30's restaurant, e-commerce, mobile-adjacent, and platform modules), this same layering is expected to hold without modification — it is domain-agnostic by design. The one architectural evolution anticipated is the package-per-module structure discussed in [ADR-002](./adr-002-modular-monolith.md)'s Future Direction, which would relocate these layers under a per-module package boundary without changing what each layer is responsible for.

## Impact on future development

- Any business rule can be found in exactly one place: the service for that domain — not scattered across an admin controller, an API controller, and a form request that each reimplement it slightly differently.
- Feature tests exercise services directly for business-rule coverage and exercise the HTTP layer for request/response contract coverage — see [ADR-013](./adr-013-testing-strategy.md) for testing expectations.
- Swapping persistence details (e.g. adding a cache layer, changing a query strategy) touches only the Eloquent repository implementation, never the service or controller.
- New contributors (human or AI) can locate "where does X happen" deterministically by asking "is this validation, authorization, business logic, or persistence" rather than searching the whole codebase.
