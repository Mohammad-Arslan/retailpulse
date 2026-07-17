# Phase 12 — TOIL (Time Off In Lieu)

**Gate / registry key:** `overtime` (earning side) + `leave` (claim side)
**Wave:** 2
**Depends on:** `hr`, `attendance`, `overtime`, `leave`
**Status (module roll-up):** Implemented
**Follows:** [overtime.md](./overtime.md), [leave.md](./leave.md)

---

## 1. Objective

Let employees bank hours worked on a rest day, off day, or public holiday instead of (or in addition to) being paid overtime, then later redeem the banked hours as a leave request or a cash payout — with a full audit trail and no possibility of double-spend under concurrent claims.

---

## 2. Actors & permissions

Mostly reuses existing permissions; two new ones for the cash-claim side:

| Permission | Use |
| :--- | :--- |
| `overtime.approve` | Approve overtime, including the cash/TOIL compensation choice; also gates reschedule (`LeaveRequestPolicy::reschedule` maps to `leave.approve`, see below) |
| `overtime.manage-policies` | Configure `compensation_type` per day-type multiplier, `toil_expiry_months` |
| `leave.request` / `leave.approve` | TOIL leave claims (leave type `TOIL`) reuse the standard leave request lifecycle, including reschedule |
| `toil.request-cash-claim` (new) | Request a TOIL cash payout |
| `toil.approve-cash-claim` (new) | Approve/reject/cancel a TOIL cash payout |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-TOIL-FR-001 | Implemented | `overtime_multipliers.compensation_type` (`cash` / `toil` / `employee_choice`) selects, per day-type, whether approved overtime pays cash, banks TOIL hours, or lets the employee choose. |
| P12-TOIL-FR-002 | Implemented | TOIL is only ever earned via the existing rest-day/public-holiday detection (`OvertimePolicy.rest_day_applies`/`public_holiday_applies`, `EmployeeShiftPreference.rest_days`) — no second, parallel day-type detector was built. |
| P12-TOIL-FR-003 | Implemented | Credited hours = `overtime_minutes / 60 * resolved_multiplier` — reuses the multiplier already resolved on the `OvertimeRecord`; there is no second conversion-ratio field. |
| P12-TOIL-FR-004 | Implemented | `employee_choice` capture happens at approval time via a `compensation_choice` field on the approve action, persisted on `OvertimeRecord` and never re-evaluated if the policy config changes afterward. |
| P12-TOIL-FR-005 | Implemented | Own append-only ledger (`toil_ledger_entries`) with `hold`/`debit` states — two concurrent claims can never both succeed against the same hours (row-locked balance check). |
| P12-TOIL-FR-006 | Implemented | Per-credit `expires_at`, independent of the annual leave fiscal-year cycle; FIFO expiry job. |
| P12-TOIL-FR-007 | Implemented | Leave claim: redeem TOIL hours as a `TOIL`-type leave request (full/half/short-leave style, unchanged duration-type machinery from the Leave module). `LeaveService::requestLeave/approve/reject/cancel` detect `leaveType.code === 'TOIL'` and delegate entirely to `ToilClaimService`/`ToilLedgerService`, bypassing `LeaveEntitlement` — the computed `days` is converted to hours via the same `work_hours_per_day` setting used for short-leave. The hold is placed in the same transaction as the `LeaveRequest` row, so an insufficient balance rolls back both. |
| P12-TOIL-FR-008 | Implemented | Cash claim: `ToilClaimService::requestCashClaim()` creates a `claim_type=cash` `ToilClaim` with no `leave_request_id` at all — verified no `leave_requests` row is ever created. Requires `LeaveType.allow_cash_claim=true` and a configured `payroll_toil_payout_component_code`; an approved claim is picked up by the next payroll run as an earning line (`PayrollCalculationService::buildToilCashClaimLines()`), converting the daily rate from the existing leave-encashment formula into an hourly rate via `work_hours_per_day` — no second rate table. |
| P12-TOIL-FR-009 | Implemented | Manager reschedule of a pending TOIL leave request (`LeaveService::reschedule()`, `leave_request_reschedules` immutable audit trail). Deliberately scoped to TOIL leave requests only, per spec — rejects with a clear error for any other leave type. Only the dates change; `days` (and therefore the already-placed TOIL hold) is left untouched, so a reschedule-then-approve or reschedule-then-reject flow can never double-touch the balance. |

---

## 4. Domain model

```text
overtime_multipliers (addition)
- compensation_type (cash | toil | employee_choice, default cash)

overtime_records (addition)
- compensation_choice nullable (cash | toil) — resolved once, at approval

overtime_policies (addition)
- toil_expiry_months nullable — null = credited hours never expire

toil_ledger_entries (append-only; the source of truth)
- id, employee_id,
  entry_type (credit | hold | debit | release | expiry | adjustment),
  hours (positive magnitude — direction implied by entry_type,
         except adjustment which may be signed; no producer of
         adjustment entries exists yet, schema is forward-compatible),
  earned_date, expires_at (credit only),
  overtime_record_id nullable (traces a credit to its origin),
  toil_claim_id nullable (traces hold/debit/release to its claim),
  credit_entry_id nullable self-FK (traces an expiry to the specific
                                     credit it consumed, for audit —
                                     NOT used by the FIFO calculation
                                     itself, which re-derives allocation
                                     from aggregate consumed totals),
  notes, created_by, timestamps

toil_balances (derived cache — never the source of truth)
- id, employee_id unique, available_hours, pending_hours, timestamps
- rebuildable from the ledger via ToilLedgerService::reconcileBalance()

toil_claims (unified claim record for both claim types — both wired)
- id, employee_id, claim_type (leave | cash), hours, status,
  leave_request_id nullable (claim_type=leave only),
  payroll_component_code nullable (claim_type=cash only, snapshotted),
  reason, approval_chain_json, approved_at, timestamps

leave_types (additions — allow_leave_claim and allow_cash_claim both
             enforced; payroll_toil_payout_component_code required for a
             cash claim to be requested at all)
- allow_leave_claim (default true), allow_cash_claim (default false),
  payroll_toil_payout_component_code nullable — payment-side counterpart
  of payroll_deduction_component_code / payroll_encashment_component_code;
  a distinct field since TOIL cash payout and ordinary leave encashment
  are different money flows that must not collide on one LeaveType row.
  A `TOIL` leave_type row is seeded idempotently for this purpose.

leave_request_reschedules
- id, leave_request_id, old_start_date, old_end_date, new_start_date,
  new_end_date, changed_by, reason, timestamps — immutable audit trail;
  the leave_requests row's dates change, this table is the history.
```

---

## 5. Services & interfaces

```text
ToilLedgerService
  credit(employee, overtimeRecord, hours, expiresAt)
  holdForClaim(employee, hours, claim)   # the single choke point for both claim types
  debit(claim) / release(claim)
  reconcileBalance(employee)             # rebuild purely from the ledger
  expireDueCredits(asOf)                 # FIFO allocation, see below

ToilClaimService
  holdForLeaveClaim(employee, leaveRequest, hours)   # called from LeaveService
  requestCashClaim(employee, leaveType, hours, reason)
  approve / reject / cancel(claim, actorUserId)      # shared across both claim types —
                                                       # the ledger operation itself doesn't
                                                       # differ by claim_type

OvertimeEngine (existing, extended)
  resolveDayType()   — now actually consults rest_day_applies + rest_days
  approveRecord()    — captures compensation_choice, credits TOIL when chosen
```

### FIFO expiry algorithm

Per employee: order all `credit` entries oldest-first by `earned_date`. Compute a single aggregate `consumedTotal = SUM(hold) − SUM(release) + SUM(expiry)` (everything that has ever left the *available* pool — `debit` is deliberately excluded since it only ever moves hours out of `pending_hours`, never `available_hours`, so including it would double-count the same hours already removed at hold time). Walk the credits allocating `consumedTotal` against each one's `[cumulativeCreditedBefore, cumulativeCreditedAfter]` slice via `min()`; a credit's unconsumed remainder is only expired if its `expires_at` has passed. This is a standard FIFO allocation (same principle as inventory cost-layer consumption already used elsewhere in this codebase) — verified with a worked multi-credit, partially-held example in `Phase12Wave2ToilLedgerTest`.

### Commands (mirroring `leave:process-year-end`)

- `toil:expire-credits {--as-of=}` — scheduled daily at 02:00, calls `ToilLedgerService::expireDueCredits()`.
- `toil:reconcile-balances` — scheduled daily at 02:15 as a drift-safety-net, calls `reconcileBalance()` for every employee with any ledger activity.

---

## 6. Domain events

No dedicated Laravel event classes were introduced — every state transition (`credit`/`hold`/`debit`/`release`/`expiry`) is already fully captured as an immutable `toil_ledger_entries` row plus the owning `ToilClaim`/`OvertimeRecord` status, which serves as the audit trail. TOIL cash claims flow through the existing `payroll.posted` pay-component path the same way `LeaveEncashment` does — no new GL posting was added for TOIL specifically.

---

## 7. Configurability surface

* `compensation_type` per day-type multiplier, `toil_expiry_months` per overtime policy, `allow_leave_claim`/`allow_cash_claim`/`payroll_toil_payout_component_code` per leave type — all table-driven, no hardcoded rules.

---

## 8. Out of scope / deferred hooks

* No admin UI for manual ledger `adjustment` entries in this pass — the entry_type and reconciliation formula are forward-compatible with one if built later.
* Country-specific TOIL statutory caps — expressed as config ceilings if ever required, not hardcoded.
