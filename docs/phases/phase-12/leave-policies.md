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

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-LVP-FR-001 | Implemented | Leave policies exist per leave_type + legal_entity (nullable = default) with effective dating. |
| P12-LVP-FR-002 | Implemented | Accrual methods supported: `fixed_annual`, `monthly_accrual`, `per_worked_hours`. |
| P12-LVP-FR-003 | Implemented | Fields: accrual_rate, max_balance, carry_forward_limit, carry_forward_expiry_months, proration_on_join. |
| P12-LVP-FR-004 | Planned | Pro-rata accrual on join/exit shall use configurable day-count basis (calendar / working days). |
| P12-LVP-FR-005 | Implemented | Encashment policy: `encashment_allowed`, `encashment_max_days` (nullable = unlimited), `encashment_requires_approval`. Rate resolution reuses the existing leave-deduction daily-rate mechanism (`LeaveType.payroll_encashment_component_code` → `PayComponent` → `basisComponent` → `config('payroll.leave_days_in_month')`) rather than a separate basic/gross literal, so there is exactly one config-driven day-rate formula for both leave deductions and leave encashment. |
| P12-LVP-FR-006 | Planned | Gender / grade / employment-type eligibility filters as optional policy JSON. |
| P12-LVP-FR-007 | Planned | Negative balance allowed flag (advances against future accrual). |
| P12-LVP-FR-008 | Planned | Minimum notice days and max consecutive days configurable. |
| P12-LVP-FR-009 | Partial | Accrual job runs on schedule for monthly_accrual / per_worked_hours (foundation may accrue on demand). |

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
  proration_on_join,
  effective_from / effective_to,
  status,
  short_leave_max_hours, short_leave_max_requests_per_month,
  out_station_deducts_balance,
  encashment_allowed, encashment_max_days, encashment_requires_approval

# Planned extensions
- eligibility_json, allow_negative_balance, min_notice_days,
  max_consecutive_days, day_count_basis

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
LeavePolicyResolver
LeaveAccrualService
LeaveEncashmentService          # Planned
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

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-LVP-AC-001 | Implemented | Changing accrual_rate via UI affects next accrual without deployment. |
| P12-LVP-AC-002 | Implemented | Effective-dated policy: prior entitlements already accrued not retro-rewritten. |
| P12-LVP-AC-003 | Implemented | Encashment creates a payroll earning line via `PayrollCalculationService::buildLeaveEncashmentLines()` (mirrors `buildLeaveDeductionLines()`), scoped to the run's period by `leave_encashments.approved_at`. |
| P12-LVP-AC-004 | Planned | Joiner mid-year gets prorated fixed_annual entitlement when proration_on_join true. |

---

## 11. Out of scope / deferred hooks

* Country statutory leave packs as seeded config only — no legal advice baked into code.
