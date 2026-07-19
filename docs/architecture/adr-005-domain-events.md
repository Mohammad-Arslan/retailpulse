# ADR-005: Domain Events

Status: Accepted

Date: 2026-07-19

Related: [ADR-002 Modular Monolith](./adr-002-modular-monolith.md) · [ADR-006 Workflow Engine](./adr-006-workflow-engine.md) · [ADR-007 Integration Hub](./adr-007-integration-hub.md) · [ADR-011 Audit History](./adr-011-audit-history.md)

---

## Why

Business actions in RetailPulse routinely have side effects that belong to a different module than the one performing the action: completing a sale should accrue loyalty points and eventually post an accounting journal; creating an employee should provision leave entitlements; a stock movement should update dashboards in real time. If every module called every other module's services directly to trigger these side effects, [ADR-002](./adr-002-modular-monolith.md)'s module boundaries would erode immediately, and the owning module would need to know about every consumer that currently cares about its actions — and every consumer that will exist after Phase 29's workflow engine and Phase 15/25's external webhooks are built.

## What

RetailPulse uses Laravel's native event system (`Illuminate\Events`) as the mechanism for cross-module and cross-cutting side effects. A module that performs a business action **dispatches a domain event**; it does not know or care who listens.

## How

### What is and isn't a domain event

A domain event represents something that **happened** in the business domain — a fact, past tense, not a command. Existing events already follow this: `SaleCompleted`, `TransferConfirmed`, `EmployeeCreated`, `EmployeeTerminated`, `InventoryStockChanged`, `LowStockAlert`, `UserLoggedIn`, `CustomerCreditLimitWarning`.

Use an event when:
- The action's side effects belong to a **different module** than the one performing the action (loyalty accrual on sale, audit logging, dashboard broadcast).
- **Multiple, independently-evolving consumers** may need to react (a `SaleCompleted` today feeds loyalty; tomorrow it also feeds accounting, and later a registered webhook per [ADR-007](./adr-007-integration-hub.md)).
- The reaction can safely happen **asynchronously** or **after** the triggering transaction commits, without the caller needing the result.

Do not use an event when:
- The caller needs a **return value** or must know synchronously whether the operation succeeded (call the service method directly).
- The "side effect" is actually core to the same module's own invariant (e.g. `InventoryService` decrementing stock as part of completing a sale is direct orchestration within/adjacent to one transaction, not an event — only the *fan-out notification* that stock changed is an event, `InventoryStockChanged`).
- It's a single, tightly-coupled call within the same module. Don't invent an event to avoid an ordinary method call inside one module's own service layer.

### Naming conventions

- **PascalCase, past tense, no `Event` suffix**: `SaleCompleted` not `SaleCompletedEvent`, `EmployeeTerminated` not `TerminateEmployeeEvent`.
- **`{Entity}{PastTenseVerb}`**: `EmployeeCreated`, `EmployeeReactivated`, `TransferConfirmed`, `OrgAssignmentChanged`.
- Namespaced under `app/Events/{Domain}/` for domain-specific events (e.g. `app/Events/Accounting/`, `app/Events/Procurement/`); flat under `app/Events/` for foundational, cross-domain events (`SaleCompleted`, `UserLoggedIn`).
- Events carry the **model(s) involved**, typically as `public readonly` constructor properties, using `Dispatchable` and `SerializesModels` — see `SaleCompleted` for the canonical minimal shape. Do not stuff unrelated data or derived computations into the event payload; listeners can load what they need from the model.
- Events that will cross a system boundary (become a webhook payload, per [ADR-007](./adr-007-integration-hub.md), or a workflow trigger, per [ADR-006](./adr-006-workflow-engine.md)) additionally get a stable **dot-separated slug** distinct from the class name — e.g. `po.created`, `leave_request.submitted`, `stock.below_reorder` (see Phase 15 and Phase 29 phase docs). The PHP class name is an internal implementation detail; the slug is the public, versioned contract external integrations and workflow definitions key off of. Don't rename a published slug — add a new one and deprecate the old, the same way an API version would.

### Publishing

A Service dispatches an event as the last step of a successful business operation, after its transaction has committed (directly, or via `DB::afterCommit()` when dispatched from inside a transaction closure — see [ADR-003](./adr-003-backend-architecture.md)). Controllers never dispatch domain events directly — dispatch is Service-owned, same as the rest of business logic, so an event can never fire for a state change that didn't actually go through the rule-enforcing layer.

### Subscribers (listeners)

- `app/Listeners/{Domain}/` mirrors the event namespace convention.
- A listener does one thing (`ProcessLoyaltyOnSaleCompleted`) — if a single event needs several unrelated reactions, register several listeners rather than growing one listener with unrelated branches.
- Listeners that do meaningful work (I/O, external calls, anything not near-instant) should implement `ShouldQueue` so the triggering request isn't blocked — audit logging and in-process broadcasts are the exception, since they're expected to be synchronous and fast.
- A listener failing must not silently swallow the failure if it represents lost business data (e.g. a missed loyalty accrual) — let it fail loudly to the queue's failure handling rather than catching broadly.

### Relationship to the Audit Observer

The audit trail ([ADR-011](./adr-011-audit-history.md)) is deliberately **not** implemented as a domain event listener — `AuditObserver` hooks Eloquent's `created`/`updated`/`deleted` model lifecycle directly, because every mutation must be audited regardless of whether a domain event was dispatched for it. Domain events are for *business-meaningful* side effects; the audit trail is a lower-level, universal guarantee. Don't rely on a domain event firing as a proxy for "this change was audited" — those are two independent mechanisms.

### Integration strategy — relationship to the future Workflow Engine and Integration Hub

Phase 29's `WorkflowEngine::initiate()` and Phase 15/25's webhook dispatcher are both, architecturally, **additional listeners** on existing or new domain events — a `PurchaseOrderCreated` event triggers both today's direct business logic and, once built, the `po.approval` workflow definition and any registered `po.created` webhook. This is why the event-slug convention above exists ahead of those phases: it lets Phase 29 and Phase 15 attach to events that already exist rather than requiring every existing action to be retrofitted with a slug at that point.

### Future event bus

Today, events are dispatched and handled in-process via Laravel's synchronous/queued event dispatcher — there is no message broker, no separate event bus service, and no event sourcing/replay capability. If RetailPulse's scale eventually demands decoupling event dispatch from the web request process entirely (e.g. a dedicated event-streaming backbone for the Integration Hub's webhook fan-out, or for a future microservice extracted per [ADR-002](./adr-002-modular-monolith.md)'s Future Direction), the event-slug convention already in place is what makes that migration additive: a slug-keyed event can be published to a broker (e.g. a Kafka/Redis Streams topic mirroring the slug) without renaming anything consumers already depend on. This is not scheduled work — it is the reason the slug convention is treated as a stable contract now, before there's an external system depending on it.

## Trade-offs

- **Harder to trace "what happens when."** Following a `SaleCompleted` dispatch to all its consequences requires knowing every registered listener — there is no single call stack to read top-to-bottom the way a direct method call chain would give you. Accepted because the alternative (direct cross-module calls) creates the coupling this ADR exists to avoid; the mitigation is disciplined naming/namespacing (above) so listeners are discoverable by convention (`app/Listeners/{Domain}/` mirrors `app/Events/{Domain}/`).
- **Queued listeners introduce eventual consistency** — a loyalty-point accrual queued off `SaleCompleted` is not visible in the same request/response cycle as the sale. Accepted for genuinely asynchronous side effects; where a caller needs a synchronous result, this ADR already says not to use an event for that case.
- **Payload discipline is a convention, not enforced by the type system** beyond the constructor's typed properties — nothing stops a future contributor from adding a derived/computed field to an event payload "for convenience." This is a documentation and review discipline, the same way [ADR-002](./adr-002-modular-monolith.md)'s namespace boundaries are.

## Alternatives considered

- **Direct service-to-service calls for all cross-module effects** — rejected as the default: it's simpler to trace but recreates exactly the tight coupling [ADR-002](./adr-002-modular-monolith.md) is designed to prevent, and doesn't scale to "N future consumers I don't know about yet" (a webhook subscriber, a workflow definition) without repeatedly modifying the producing module.
- **A dedicated message broker (Kafka, RabbitMQ, Redis Streams) from the start** — rejected for the current stage: it adds an operational dependency and a second failure domain for a codebase that doesn't yet have a concrete requirement (cross-process fan-out, replay, multi-service consumers) the in-process Laravel event system can't satisfy. The event-slug convention keeps this option open (see Future Event Bus above) without paying its operational cost now.
- **Event sourcing (state reconstructed by replaying an event log) as the persistence model** — rejected as the system-wide persistence strategy: RetailPulse's audit/history requirements are already met by the reversal-based approach in [ADR-011](./adr-011-audit-history.md), which is far simpler to reason about for financial correctness than reconstructing state from a replayed stream. Domain events here are a notification mechanism, not the source of truth for entity state.

## Future direction

As new domains ship (restaurant, e-commerce, mobile), each introduces its own event vocabulary under the same naming and slug conventions — no domain gets an exception. When the Integration Hub ([ADR-007](./adr-007-integration-hub.md)) and Workflow Engine ([ADR-006](./adr-006-workflow-engine.md)) ship, they consume this existing event catalog rather than inventing a parallel one.

## Impact on future development

- Modules stay decoupled: Checkout does not import Loyalty's or Accounting's service classes.
- New consumers (a webhook, a workflow trigger, a new report) attach to an existing event without touching the module that dispatches it.
- Event payload changes are a shared-contract change — treat adding a new public property as safe, removing or renaming one as breaking for every listener and, once external webhooks exist, for external integrators too.
