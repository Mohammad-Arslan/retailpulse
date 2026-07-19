# ADR-013: Testing Strategy

Status: Accepted

Date: 2026-07-19

Related: [ADR-003 Backend Architecture](./adr-003-backend-architecture.md) · [ADR-012 Development Standards](./adr-012-development-standards.md) · [ADR-010 Security](./adr-010-security.md)

---

## Why

RetailPulse's business rules (leave accrual edge cases, invoice sequence race conditions, tax priority resolution, RBAC boundaries) are exactly the kind of logic that "looks right" in a manual click-through and quietly breaks the second time someone touches an adjacent file. With 30 phases of roadmap and a mix of human and AI contributors who don't share continuous memory of the codebase's edge cases, the test suite — not a person's recollection — is what actually stops old bugs from coming back and new business rules from shipping unverified.

## What

Feature tests are the primary test type for business logic, because most of RetailPulse's rules involve real database state (branch/tenant scoping, RBAC, multi-row calculations) that a mocked unit test cannot faithfully exercise. Unit tests are reserved for logic genuinely isolable from the framework. Every layer of the test pyramid has a defined purpose — none is optional decoration.

## How

### Unit tests (`tests/Unit/`)

For logic genuinely isolable from the database/framework: a pure calculation, a value object, an algorithm (`RoleServiceTest`). If a "unit test" needs `RefreshDatabase` or seeded RBAC data to pass, it is a Feature test mislabeled — move it.

### Feature tests (`tests/Feature/`)

The dominant, expected test type for business-rule coverage (already the majority of the suite: Accounting, Admin, Auth, Checkout, Loyalty, Pos, Procurement, Phase12). Every Service method with a business rule gets a Feature test exercising the rule's boundary conditions — not just the happy path through the controller. Example: `tests/Feature/Phase12/Phase12Wave2LeaveAccrualTest.php` covers leave accrual edge cases, not just "accrual happens."

Shared test setup goes in `tests/Concerns/` (`SeedsRbac`, `SeedsAccounting`, `SeedsLoyaltyEngine`) as traits, not copy-pasted per test file — if a third test needs the same seed data as two existing tests, extract a concern rather than duplicating the setup a third time.

### Integration tests (`tests/Integration/`)

Reserved for cross-service/cross-module flows that a single Feature test on one controller wouldn't exercise end-to-end — e.g. a full loyalty-engine flow spanning cart → sale → point accrual → tier recalculation (see `tests/Integration/Loyalty/`). Use this tier when the thing under test is the *interaction* between modules via domain events ([ADR-005](./adr-005-domain-events.md)), not any single module's internal logic (that's a Feature test) and not the whole HTTP stack for its own sake (that's still a Feature test too, just for one controller).

### Browser tests

Not yet part of the standard suite. When end-to-end browser coverage becomes necessary (a POS keyboard-flow regression, a multi-step Inertia wizard), it is added deliberately (e.g. Laravel Dusk or Playwright) scoped to the highest-value user journeys — not as blanket coverage duplicating what Feature tests already verify at the HTTP layer. Until then, the `verify` skill's "drive the actual flow and observe behavior" step is the substitute for genuinely new UI-facing changes.

### Performance tests

Not a routine per-PR requirement, but required for specific high-risk paths identified in [ADR-014](./adr-014-performance.md) — the invoice sequence race-safety (`FOR UPDATE` lock), bulk import throughput, and any query flagged as N+1-prone. These are targeted benchmarks/regression guards on a known-risky path, not a general performance test suite run on every change.

### Security tests

RBAC/Policy boundary tests are Feature tests by another name — e.g. "a `sales:read`-scoped token cannot create a user" (the Phase 15 acceptance criterion) is exactly the shape of test this ADR already requires for any permission-gated feature. Additionally: webhook signature verification ([ADR-007](./adr-007-integration-hub.md)) and tenant-isolation boundaries (post-Phase-28, [ADR-001](./adr-001-saas-multi-tenancy.md)) get dedicated negative tests — proving the *rejection* path works, not just the happy path.

### Coverage expectations

There is no single blanket line-coverage percentage target used as the *primary* quality mechanism — coverage-by-number invites hollow tests written to hit a metric rather than to verify a rule. This is not a contradiction of Phase 16's planned CI gate (90%+ line coverage on Services/Repositories/DTOs, blocking merge below threshold, per `docs/phases/phase-16-hardening-deployment.md`) — that gate is a **backstop floor** enforced once CI exists, catching the case where a whole class went untested; it does not replace the requirement below that every business rule specifically has boundary-condition coverage. A Service could hit 90% line coverage while still missing the one edge case that matters — the rule-based requirement below is what actually prevents that; the percentage gate is what prevents wholesale untested code from merging at all. Both apply once Phase 16 ships; only the rule-based requirement applies before CI exists.

Instead:

- Every business rule identified during Service design ([ADR-003](./adr-003-backend-architecture.md)) has at least one Feature test for its boundary conditions — this is the actual, checked expectation.
- A migration or model change that affects an existing audited/financial workflow ([ADR-011](./adr-011-audit-history.md)) is not considered complete until the existing feature tests for that domain still pass — these tests are the executable specification of the business rule, not incidental scaffolding.
- A bug fix includes a regression test reproducing the bug before the fix, passing after — a fix without a test is presumed to recur.

### Running tests

`composer test` / `php artisan test` (whole suite or `--filter=TestName` / a specific directory) before considering backend work done; `./vendor/bin/pint` before considering it committed. **Note:** per repository convention (`.cursor/rules/retailpulse-core.mdc`), an AI coding agent does not run the test suite itself unless explicitly asked — it states what to run and lets the user execute it. This is a workflow rule about who presses the button, not an exemption from the coverage expectations above.

## Trade-offs

- **Feature tests are slower than mocked unit tests** (real database, real seeded RBAC data) — accepted because the speed cost is far cheaper than the correctness cost of a mocked test that passes while the real business rule is broken, which is the specific failure mode this ADR is designed against.
- **No blanket coverage percentage means coverage can't be checked by a single CI gate number** — accepted because a percentage target is gameable (testing getters/setters to inflate a number) in a way that "does every business rule have a boundary-condition test" is not; the latter requires actual reviewer judgment, which this ADR treats as worth the cost.

## Alternatives considered

- **Mocked-service unit tests as the primary test type** (mock every repository/service dependency, test controllers/services in isolation) — rejected as the default: RetailPulse's bugs have historically clustered around real cross-row, cross-branch, cross-tenant interactions that mocks paper over by construction. Reserved for the narrower "genuinely isolable logic" case (see Unit tests above).
- **A strict line/branch coverage percentage gate in CI** — rejected per Coverage Expectations above; considered too easy to satisfy with low-value tests and too blunt an instrument for a business-rule-heavy codebase.
- **Snapshot/golden-file testing for Inertia page props** — considered for catching accidental prop-shape changes; not adopted as a default because it tends to produce noisy, low-signal test failures on legitimate intentional changes (a snapshot updated so often it stops being reviewed carefully). A Feature test asserting the specific props a page needs is preferred where prop shape matters.

## Future direction

As browser-level and performance-regression testing needs become concrete (a specific POS flow that's broken twice, a specific query that's regressed twice), they are added as scoped, named suites targeting exactly that risk — not as a blanket initiative to "add E2E tests" or "add perf tests" disconnected from an actual recurring failure.

## Impact on future development

- A new Service method implementing a business rule is not "done" without its Feature test — this is checked in review per [ADR-012](./adr-012-development-standards.md)'s review process.
- Regressions in leave accrual, invoice sequencing, tax calculation, and RBAC boundaries are caught by the existing suite before they reach a business, not discovered by a support ticket.
- A contributor (human or AI) unsure whether a change is complete can ask "is there a test proving the specific rule I just implemented, including its edge cases" as a concrete, checkable bar.
