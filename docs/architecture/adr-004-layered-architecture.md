# ADR-004: Layered Backend Architecture

Status: Accepted

Date: 2026-07-19

Related: [ADR-002 Modular Architecture](./adr-002-modular-architecture.md) · [ADR-011 Development Standards](./adr-011-development-standards.md) · [ADR-010 Security Principles](./adr-010-security-principles.md)

---

# Context

Laravel does not enforce a layering discipline out of the box — it is entirely possible to put validation, business logic, and query building all inside a controller method. That works for a small app; it does not survive an ERP with 68+ services, 60+ policies, and modules as complex as HR/Payroll (`docs/phases/phase-12/`). Without an enforced layering convention, business rules end up duplicated between the admin controller and the API controller for the same entity, validation logic drifts from what the database actually allows, and testing requires spinning up the full HTTP stack because logic isn't isolated anywhere else.

# Decision

Every admin/API write path follows this layering, in this order:

```
Route → Middleware → Controller → FormRequest → Service → Repository → Model
                                        ↓
                                      DTO (passed between Service and Repository/other services)
```

## Responsibility per layer

**Route** (`routes/admin.php`, `routes/api.php`) — binds a URI + HTTP verb to a controller action, attaches route-level middleware (`auth`, `EnsureAdminAccess`, `SetBranchContext`, module-gate middleware like `EnsureHrModuleEnabled`), and names the route. No logic.

**Middleware** — cross-cutting concerns that apply before the controller runs: authentication, branch context resolution (`SetBranchContext`), module-enabled gates (`EnsureHrModuleEnabled`, `EnsureAccountingModuleEnabled`), locale (`SetLocale`). Middleware does not contain business logic specific to one feature.

**Controller** (`app/Http/Controllers/Admin/{Domain}/`, `app/Http/Controllers/Api/V1/{Domain}/`) — thin. A controller action:
1. Type-hints a `FormRequest` (validation already ran by the time the method body executes).
2. Calls exactly one service method (occasionally orchestrates 2–3 for a single use case, e.g. create-then-redirect), passing DTOs/primitives, not raw `Request` objects, into the service.
3. Returns an `Inertia::render(...)` response (admin) or a JSON resource/response (API).

A controller must never: build an Eloquent query itself, contain a validation rule, contain a business rule ("if this employee's grade allows...", "if this sale total exceeds..."), or catch a domain exception to paper over it silently.

**FormRequest** (`app/Http/Requests/Admin/{Domain}/`, `app/Http/Requests/Api/...`) — owns **input validation and request-level authorization**. `authorize()` typically delegates to a Policy (see [ADR-010](./adr-010-security-principles.md)); `rules()` defines shape/type/format validation. A FormRequest validates that the *request is well-formed and the actor may submit it* — it does not validate business invariants that require loading other records or cross-entity state (e.g. "this leave balance is sufficient" belongs in the Service, because it requires the entitlement record, not just the request payload).

**Service** (`app/Services/{Domain}/`) — owns **business logic**: orchestration across repositories, business-rule enforcement, transaction boundaries (`DB::transaction`), dispatching domain events ([ADR-003](./adr-003-domain-events.md)), and translating between DTOs and repository calls. This is where "is this leave balance sufficient," "does this discount exceed the manager-approval threshold," and "recompute the invoice sequence under a row lock" live. A service method is the natural seam for a feature test — it should be callable and testable without an HTTP request.

**Repository** (`app/Repositories/Contracts/{X}RepositoryInterface.php`, `app/Repositories/Eloquent/{X}Repository.php`) — owns **persistence and query construction**. Services depend on the interface (bound in `AppServiceProvider`), never the Eloquent implementation directly, so persistence can be swapped or mocked in tests without touching service logic. A repository method returns models/collections/DTOs; it does not contain business rules about *whether* an operation should happen, only *how* to fetch/persist.

**DTO** (`app/DTOs/`) — typed data carried between layers (Service ↔ Repository, Service ↔ Service) so that neither layer needs to know the shape of a `Request` or an Eloquent model to do its job. Prefer a DTO over passing an associative array across a layer boundary — arrays lose type safety and IDE/static-analysis support that a `readonly` DTO class gives you.

**Model** (`app/Models/`) — Eloquent relationships, casts, scopes, and accessors that describe *what the data is*, not what should happen to it. Model observers (`AuditObserver`) are the one sanctioned exception to "models don't contain business logic" — audit logging is a universal, cross-cutting concern by design (see [ADR-005](./adr-005-audit-trail.md)), not a feature-specific rule.

## Validation responsibility, precisely

| Validation type | Owner |
| :--- | :--- |
| Field required / type / format / max length | FormRequest `rules()` |
| Uniqueness / exists-in-database checks scoped to the request | FormRequest `rules()` (`Rule::unique`, `Rule::exists`) |
| "May this actor perform this action at all" | FormRequest `authorize()` → Policy |
| Cross-entity business invariants (balance sufficiency, threshold checks, state-machine legality) | Service |
| Data-integrity constraints only the database can guarantee (FK, unique index) | Migration/schema, as a backstop — never the *only* layer enforcing a rule the user should get a friendly error for |

# Consequences

- Any business rule can be found in exactly one place: the service for that domain — not scattered across an admin controller, an API controller, and a form request that each reimplement it slightly differently.
- Feature tests exercise services directly for business-rule coverage and exercise the HTTP layer for request/response contract coverage — see [ADR-011](./adr-011-development-standards.md) for testing expectations.
- Swapping persistence details (e.g. adding a cache layer, changing a query strategy) touches only the Eloquent repository implementation, never the service or controller.
- New contributors (human or AI) can locate "where does X happen" deterministically by asking "is this validation, authorization, business logic, or persistence" rather than searching the whole codebase.
