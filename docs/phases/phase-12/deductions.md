# Phase 12 — Deductions

**Gate / registry key:** `payroll`  
**Wave:** 3  
**Depends on:** `payroll`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Model recurring and one-time deductions as pay components and/or dedicated deduction assignments (loans, advances, garnishments, voluntary) without hardcoding deduction logic in payroll orchestration.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `payroll.manage-components` | Deduction components |
| `payroll.manage-deductions` | Assign recurring deductions (Planned) |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-DED-FR-001 | Implemented | Deduction components are pay_components with type=deduction. |
| P12-DED-FR-002 | Implemented | Unpaid leave and OT do not hardcode deduction math beyond component resolution. |
| P12-DED-FR-003 | Planned | Recurring deduction assignments: employee, component, amount/rate, start/end, priority. |
| P12-DED-FR-004 | Planned | Garnishment / third-party payments with payable mapping keys. |
| P12-DED-FR-005 | Planned | Advance/loan recoveries register as deductions linked to source documents. |
| P12-DED-FR-006 | Planned | Priority order when net pay insufficient — configurable shortfall policy (skip / partial / block run). |
| P12-DED-FR-007 | Planned | Historical open deduction schedules importable. |

---

## 4. Domain model

```text
# Implemented via pay_components type=deduction

employee_deduction_assignments              # Planned
- id, employee_id, pay_component_id,
  amount_or_rate, calculation_type,
  start_period, end_period nullable,
  source_type nullable, source_id nullable,   # loan/advance
  priority, status, timestamps
```

---

## 5. Services & interfaces

```text
DeductionAssignmentService       # Planned
PayrollCalculationService        # applies deduction components
```

---

## 6. Domain events

Applied via `payroll.posted`.

---

## 7. Configurability surface

* Components, priorities, shortfall policy — config.

---

## 8. Historical migration inputs

* Open schedules; YTD deducted amounts if needed.

---

## 9. Reports / ESS touchpoints

* Deduction register; ESS sees lines on payslip.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-DED-AC-001 | Implemented | Deduction component appears on payslip when in structure/run. |
| P12-DED-AC-002 | Planned | Loan recovery assignment generates matching deduction until balance zero. |
| P12-DED-AC-003 | Planned | Shortfall policy `block` prevents posting when net would go negative. |

---

## 11. Out of scope / deferred hooks

* Court-order legal formatting packs — config templates only.
