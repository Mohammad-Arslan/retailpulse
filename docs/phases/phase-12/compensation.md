# Phase 12 — Compensation Outcomes

**Gate / registry key:** `appraisal`  
**Wave:** 4  
**Depends on:** `hr` (optionally `payroll`, `appraisal`)  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Translate appraisal (or standalone) recommendations into salary revisions, promotions, and one-time awards with approval — applied to payroll via structure changes or adjustments, never ad-hoc GL.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `compensation.recommend` / `approve` / `apply` | Lifecycle |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-COMP-FR-001 | Planned | Compensation cycles linked optionally to appraisal cycles. |
| P12-COMP-FR-002 | Planned | Recommendation types: salary_revision, promotion (grade/designation), bonus, allowance_change. |
| P12-COMP-FR-003 | Planned | Budget pools per entity/department (configurable). |
| P12-COMP-FR-004 | Planned | Approval chains configurable. |
| P12-COMP-FR-005 | Planned | Apply creates effective-dated salary structure / grade changes and/or payroll adjustments. |
| P12-COMP-FR-006 | Planned | Grade band enforcement when grades.enforce_salary_band true. |
| P12-COMP-FR-007 | Planned | Historical compensation letters/outcomes importable. |

---

## 4. Domain model

```text
compensation_cycles
- id, code, name, appraisal_cycle_id nullable, budget_total, status

compensation_recommendations
- id, cycle_id, employee_id, type, proposed_amount_or_percent,
  new_grade_id nullable, new_designation_id nullable,
  status, appraisal_result_id nullable

compensation_applications
- id, recommendation_id, applied_at, salary_structure_id nullable,
  payroll_adjustment_id nullable
```

---

## 5. Services & interfaces

```text
CompensationService
CompensationPayrollBridge
```

---

## 6. Domain events

```text
compensation.recommended
compensation.approved
compensation.applied
```

---

## 7. Configurability surface

* Types, budgets, approval, band enforcement — config.

---

## 8. Historical migration inputs

* Past revisions as assignment history.

---

## 9. Reports / ESS touchpoints

* Merit matrix; ESS view applied letter (Planned).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-COMP-AC-001 | Planned | Apply does not write GL; pay change appears next payroll via structure/adjustment. |
| P12-COMP-AC-002 | Planned | Over-budget approval blocked unless override permission. |

---

## 11. Out of scope / deferred hooks

* Equity grants — out of scope.
