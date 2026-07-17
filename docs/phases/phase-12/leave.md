# Phase 12 — Leave Management

**Gate / registry key:** `leave`  
**Wave:** 2  
**Depends on:** `hr`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

Policy detail: [leave-policies.md](./leave-policies.md). Fiscal year behaviour: [leave-fiscal-year.md](./leave-fiscal-year.md).

---

## 1. Objective

Manage leave types, entitlements, and requests with approval, balance updates, and payroll coupling only via configurable deduction components — never hardcoded salary cuts.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `leave.view` | View balances/requests |
| `leave.request` | Create own/other (per policy) |
| `leave.approve` | Approve/reject |
| `leave.manage-types` | CRUD leave types |
| `leave.manage-policies` | Policies (see leave-policies.md) |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-LV-FR-001 | Implemented | Leave types are seeded (Annual/Sick/Unpaid/etc.) and fully editable. |
| P12-LV-FR-002 | Implemented | Leave type flags: `is_paid`, `affects_payroll`. |
| P12-LV-FR-003 | Implemented | Entitlements track accrued, used, carried_forward, remaining (derived) per employee/type/fiscal_year. |
| P12-LV-FR-004 | Implemented | Leave requests store dates, days, reason, status (pending/approved/rejected/cancelled). |
| P12-LV-FR-005 | Implemented | Approval updates entitlement `used_days`. |
| P12-LV-FR-006 | Implemented | Unpaid / over-balance leave feeds payroll as a configured deduction component (not hardcoded reduction). |
| P12-LV-FR-007 | Partial | Approval chain configurable (`approval_chain_json`); full workflow = Phase 29. |
| P12-LV-FR-008 | Implemented | Day count respects public-holiday exclusion per leave policy + holiday calendar, and weekend exclusion (`leave_policies.exclude_weekends`, default true) using configurable weekly off-days resolved through a 3-level hierarchy — employee override (`EmployeeShiftPreference.weekend_days`, HR-configurable per employee) → branch default (`Branch.weekend_days`) → legal-entity default (`HrEntitySetting.settings_json.weekend_days`) → `[0, 6]` (Sun/Sat) if nothing is configured. An empty array is a valid, deliberate "no weekly off day" setting at any level (e.g. a departmental-store branch that trades every day), not treated as unset. |
| P12-LV-FR-009 | Implemented | `duration_type` (`full_day` / `half_day` / `short_leave` / `out_station`) on every request. Half day: single date, requires `session` (morning/afternoon), always 0.5 days. Short leave: single date, requires `start_time`/`end_time`, counts as `hours ÷ work_hours_per_day` (per legal entity, `HrEntitySetting.settings_json.work_hours_per_day`, default 8) — capped per policy by `short_leave_max_hours` and `short_leave_max_requests_per_month` (nullable = unlimited; the monthly quota counts `pending` + `approved` requests). Out Station: full-day equivalent for attendance/approval, but only deducts from the leave balance when the resolved policy's `out_station_deducts_balance` is enabled — the resolved flag is snapshotted onto the request as `deduct_from_balance` at submission time so a later policy change never changes the outcome of an already-submitted request. |
| P12-LV-FR-010 | Implemented | Encashment requests convert balance to a payroll earning component (see leave-policies). `LeaveEncashmentService` handles request/approve/reject/cancel with the same double-spend protections as leave requests (per-employee row lock during request, entitlement row lock during approve/cancel). |
| P12-LV-FR-011 | Planned | Carry-forward processing at fiscal year boundary (see leave-fiscal-year). |
| P12-LV-FR-012 | Planned | Historical leave balance and request import. |
| P12-LV-FR-013 | Implemented | Leave module gated behind `leave` requiring `hr`. |

---

## 4. Domain model

```text
leave_types
- id, code, name, is_paid, affects_payroll, status

leave_entitlements
- id, employee_id, leave_type_id, fiscal_year_id,
  accrued_days, used_days, carried_forward_days,
  remaining_days (derived)

leave_requests
- id, employee_id, leave_type_id, start_date, end_date,
  duration_type (full_day / half_day / short_leave / out_station, default full_day),
  session (morning / afternoon, half_day only), start_time, end_time (short_leave only),
  days, deduct_from_balance (snapshotted at submission), reason,
  status (pending / approved / rejected / cancelled),
  approval_chain_json, timestamps

leave_policies (additions)
- short_leave_max_hours nullable, short_leave_max_requests_per_month nullable,
  out_station_deducts_balance (default false),
  exclude_weekends (default true)

hr_entity_settings.settings_json (additions)
- work_hours_per_day (default 8), weekend_days (default [0, 6] = Sun/Sat; legal-entity-wide default)

branches (additions)
- weekend_days nullable — branch-level weekly off-days default, overrides the legal-entity default
  when set (including an explicit empty array); falls through to it when null

employee_shift_preferences (additions)
- weekend_days_enabled (default false), weekend_days nullable — HR-configurable per-employee
  weekly off-days override for leave day-counting. Deliberately separate from the existing
  `rest_days` field, which drives overtime rest-day-premium detection only (see toil.md /
  overtime.md) and is populated by default for every employee, so it cannot double as an
  unset-vs-explicitly-empty signal for this override.

leave_encashments
- id, employee_id, leave_type_id, leave_policy_id, fiscal_year_id nullable,
  days, payroll_component_code, reason, status, approved_at,
  approval_chain_json, timestamps
```

Policies table: [leave-policies.md](./leave-policies.md).

---

## 5. Services & interfaces

```text
LeaveService
  request / approve / reject / cancel / recomputeBalance
LeavePayrollBridge
  resolveUnpaidDeductionComponent(employee, period)
```

---

## 6. Domain events

```text
leave.requested
leave.approved
leave.rejected
leave.cancelled
leave.encashment_posted          # Planned — may map to payroll adjustment, not always GL alone
```

---

## 7. Configurability surface

* Types, paid flags, payroll component codes, approval chain — config.

---

## 8. Historical migration inputs

* Opening entitlements; historical approved requests (optional immutable).

---

## 9. Reports / ESS touchpoints

* Balance report; ESS request + balance ([employee-self-service.md](./employee-self-service.md)).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-LV-AC-001 | Implemented | Approval increments used_days and reduces remaining. |
| P12-LV-AC-002 | Implemented | Unpaid leave appears as deduction component in payroll run. |
| P12-LV-AC-003 | Implemented | Leave spanning public holiday excludes holiday when policy configured. |
| P12-LV-AC-004 | Implemented | Cancelled request does not consume balance. |

---

## 11. Out of scope / deferred hooks

* ~~Comp-off auto-generation from OT — optional link to overtime module later.~~ Implemented — see [toil.md](./toil.md). The `TOIL` leave type is a balance source routed through `ToilLedgerService`/`ToilClaimService`, not `LeaveEntitlement`; `LeaveService` detects `leaveType.code === 'TOIL'` and delegates accordingly.
