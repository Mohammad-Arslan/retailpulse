# Phase 12 — Leave Policies

**Gate / registry key:** `leave`  
**Wave:** 2  
**Depends on:** `hr`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Define configurable accrual, proration, carry-forward, encashment, and eligibility rules per leave type and legal entity — nothing hardcoded.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `leave.manage-policies` | CRUD policies |
| `leave.manage-entitlements` | View and manually adjust an employee's leave entitlement balance (Admin → Leave Entitlements) |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-LVP-FR-001 | Implemented | Leave policies exist per leave_type + legal_entity (nullable = default) with effective dating. |
| P12-LVP-FR-002 | Implemented | Accrual methods supported: `fixed_annual`, `monthly_accrual`, `per_worked_hours`. |
| P12-LVP-FR-003 | Implemented | Fields: accrual_rate, max_balance, carry_forward_limit, carry_forward_expiry_months, proration_on_join. |
| P12-LVP-FR-004 | Partial | Pro-rata accrual on join is implemented for `fixed_annual` policies on calendar-day basis (see FR-009); a configurable `day_count_basis` (calendar vs. working days) and exit-side proration remain Planned. |
| P12-LVP-FR-005 | Implemented | Encashment policy: `encashment_allowed`, `encashment_max_days` (nullable = unlimited), `encashment_requires_approval`. Rate resolution reuses the existing leave-deduction daily-rate mechanism (`LeaveType.payroll_encashment_component_code` → `PayComponent` → `basisComponent` → `config('payroll.leave_days_in_month')`) rather than a separate basic/gross literal, so there is exactly one config-driven day-rate formula for both leave deductions and leave encashment. |
| P12-LVP-FR-006 | Planned | Gender / grade / employment-type eligibility filters as optional policy JSON. |
| P12-LVP-FR-007 | Implemented | `negative_leave_balance_policy` per leave policy (`block` default / `warn` / `allow`) — superset of the originally-planned single "allowed" flag. Checked in `LeaveService::requestLeave()` at submission and again in `LeaveService::approve()` right before the balance actually moves (a request valid at submission can still be blocked/warned at approval if another request was approved in between). `block` rejects (`ValidationException` at submission, `DomainException` at approval, since the approve action has no bound form to surface a validation error on); `warn` persists a `balance_warning` flag on the `LeaveRequest` for the approver to see; `allow` applies no enforcement, matching the "advance against future accrual" case this FR originally described. No policy resolved for the leave type at all (e.g. no `LeavePolicy` row) skips enforcement entirely, consistent with every other per-policy check in `requestLeave()`. |
| P12-LVP-FR-008 | Planned | Minimum notice days and max consecutive days configurable. |
| P12-LVP-FR-009 | Implemented | Accrual is posted for all three methods: `fixed_annual` grants the full `accrual_rate` once — at a new hire's first-ever entitlement (`LeaveService::resolveEntitlement()`, prorated when `proration_on_join` is true) and again at each year-end rollover (`LeaveFiscalYearService::closeEntitlement()`, never prorated there). `monthly_accrual` and `per_worked_hours` are posted by the daily-scheduled `leave:process-accrual` command (`LeaveService::processAccrual()`), which tracks progress per entitlement via `accrual_last_run_on` so a run is never double-counted; `per_worked_hours` sums `attendance_records.worked_minutes` for closed records in the elapsed window. All grants are capped at the policy's `max_balance` when set. |

---

## 4. Domain model

```text
leave_policies
- id, leave_type_id, legal_entity_id nullable,
  accrual_method (fixed_annual / monthly_accrual / per_worked_hours),
  accrual_rate,
  max_balance nullable,
  carry_forward_limit nullable,
  carry_forward_expiry_months nullable,
  negative_leave_balance_policy (block / warn / allow, default block),
  proration_on_join,
  effective_from / effective_to,
  status,
  short_leave_max_hours, short_leave_max_requests_per_month,
  out_station_deducts_balance,
  encashment_allowed, encashment_max_days, encashment_requires_approval

leave_requests (addition)
- balance_warning boolean default false — set when the negative-leave-balance
  policy is `warn` and the request (at submission or, again, at approval)
  would draw the entitlement below zero. Never set for TOIL, which draws from
  the separate TOIL ledger and never consults this policy.

# Planned extensions
- eligibility_json, min_notice_days, max_consecutive_days, day_count_basis
  (configurable calendar/working-day toggle — accrual proration today is
  always calendar-day)

leave_entitlements (addition)
- accrual_last_run_on nullable date — tracks the point up to which
  monthly_accrual/per_worked_hours have been posted for this entitlement, so
  the daily accrual command never double-counts a month or a worked-hours
  window. Not meaningfully used by fixed_annual (a one-shot grant), set for
  data consistency anyway.

leave_types (addition)
- payroll_encashment_component_code nullable — payment-side counterpart of
  payroll_deduction_component_code; resolves the PayComponent used to compute
  the daily rate for an approved encashment.

leave_encashments
- id, employee_id, leave_type_id, leave_policy_id, fiscal_year_id nullable,
  days, payroll_component_code (snapshotted at request time),
  reason, status (pending/approved/rejected/cancelled), approved_at,
  approval_chain_json, timestamps

leave_entitlements (addition)
- encashed_days — tracked separately from used_days so "taken as leave" and
  "cashed out" are never conflated in balance reporting/disputes.
```

---

## 5. Services & interfaces

```text
LeaveService::resolveLeavePolicy()      # policy resolution (most-specific-active-wins)
LeaveService::resolveEntitlement()      # entitlement creation; grants fixed_annual on new hire
LeaveService::processAccrual()          # monthly_accrual / per_worked_hours, called by leave:process-accrual (daily)
LeaveFiscalYearService::closeEntitlement()  # re-grants fixed_annual at year-end, see leave-fiscal-year.md
LeaveEncashmentService                  # Implemented — see leave.md
```

---

## 6. Domain events

```text
leave_policy.changed
leave.accrual_posted
```

---

## 7. Configurability surface

* All rates, methods, limits, encashment — data; no hardcoded annual days.

---

## 8. Historical migration inputs

* Policy catalogue optional; opening balances more critical.

---

## 9. Reports / ESS touchpoints

* Policy summary for auditors.
* Admin → Leave Entitlements: view accrued/used/carried-forward/encashed/remaining per employee+leave type, with manual adjustment (`accrued_days`/`carried_forward_days`) for opening balances and corrections. No dedicated adjustment ledger — audit trail is the existing generic `AuditObserver` (before/after/actor/timestamp) already registered on `LeaveEntitlement`.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-LVP-AC-001 | Implemented | Changing accrual_rate via UI affects next accrual without deployment. |
| P12-LVP-AC-002 | Implemented | Effective-dated policy: prior entitlements already accrued not retro-rewritten. |
| P12-LVP-AC-003 | Implemented | Encashment creates a payroll earning line via `PayrollCalculationService::buildLeaveEncashmentLines()` (mirrors `buildLeaveDeductionLines()`), scoped to the run's period by `leave_encashments.approved_at`. |
| P12-LVP-AC-004 | Implemented | Joiner mid-year gets a prorated fixed_annual entitlement when proration_on_join is true (calendar-day basis; `hire_anniversary` fiscal mode is never prorated since the employee's "year" begins at the hire date). |

---

## 11. Out of scope / deferred hooks

* Country statutory leave packs as seeded config only — no legal advice baked into code.
