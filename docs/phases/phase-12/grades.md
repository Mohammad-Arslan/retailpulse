# Phase 12 — Grades

**Gate / registry key:** `hr`  
**Wave:** 1  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Define pay grades / bands used for compensation ranges, eligibility rules (leave, loan limits, appraisal), and reporting.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `hr.manage-org` | CRUD grades |
| `payroll.manage-structures` | Link grades to salary structures (optional) |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-GRD-FR-001 | Planned | The system shall support grades with code, name, rank/sequence, legal_entity_id nullable, status. |
| P12-GRD-FR-002 | Planned | Grade may define min/mid/max compensation guidance amounts (currency = entity functional); informational or policy-enforced by config flag. |
| P12-GRD-FR-003 | Planned | Grades are effective-dated (`effective_from` / `effective_to`) for band changes. |
| P12-GRD-FR-004 | Planned | Employees reference grade_id; history retained on change. |
| P12-GRD-FR-005 | Planned | Loan / advance eligibility policies may reference grade (see salary-advance, employee-loans). |
| P12-GRD-FR-006 | Planned | Grades are importable. |

---

## 4. Domain model

```text
grades
- id
- legal_entity_id nullable
- code
- name
- rank
- currency_code nullable
- min_amount nullable / mid_amount nullable / max_amount nullable
- enforce_salary_band boolean default false
- effective_from / effective_to nullable
- status
- timestamps
```

---

## 5. Services & interfaces

```text
GradeService
GradePolicyResolver          # resolve active grade for employee/date
```

---

## 6. Domain events

```text
grade.created
grade.updated
```

---

## 7. Configurability surface

* Band amounts, enforcement flag, rank — all config; no hardcoded grade ladders.

---

## 8. Historical migration inputs

* Grade catalogue and employee grade codes.

---

## 9. Reports / ESS touchpoints

* Headcount and avg pay by grade ([reports.md](./reports.md)).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-GRD-AC-001 | Planned | When `enforce_salary_band` is true, assigning structure amounts outside min/max is blocked or warns per policy. |
| P12-GRD-AC-002 | Planned | Overlapping effective grade definitions for same code are rejected. |

---

## 11. Out of scope / deferred hooks

* Automatic mid-point salary suggestions — optional future; not required for MVP grade CRUD.
