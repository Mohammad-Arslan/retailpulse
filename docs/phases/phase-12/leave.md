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
| P12-LV-FR-008 | Partial | Day count respects public-holiday exclusion per leave policy + holiday calendar; weekend exclusion remains Wave 2. |
| P12-LV-FR-009 | Planned | Half-day / hourly leave units when policy allows. |
| P12-LV-FR-010 | Planned | Encashment requests convert balance to pay component (see leave-policies). |
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
- id, employee_id, leave_type_id, start_date, end_date, days, reason,
  status (pending / approved / rejected / cancelled),
  approval_chain_json, timestamps

# Planned
leave_encashments
- id, employee_id, leave_type_id, fiscal_year_id, days, amount,
  payroll_run_id nullable, status, timestamps
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

* Comp-off auto-generation from OT — optional link to overtime module later.
