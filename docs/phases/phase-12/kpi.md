# Phase 12 — KPI Management

**Gate / registry key:** `appraisal`  
**Wave:** 4  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Define measurable KPIs assignable to employees/roles/grades with targets, weightings, and period scores — fully configurable catalogues.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `appraisal.manage-kpi` | CRUD KPIs / assignments |
| `appraisal.view` | View scores |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-KPI-FR-001 | Planned | KPI library: code, name, description, unit, polarity (higher_better/lower_better), status. |
| P12-KPI-FR-002 | Planned | Assignment to employee / designation / grade / department with weight %, target, period. |
| P12-KPI-FR-003 | Planned | Period scores (actual, score%, evidence notes). |
| P12-KPI-FR-004 | Planned | Weighted roll-up feeds appraisal cycle. |
| P12-KPI-FR-005 | Planned | Effective-dated KPI definitions. |
| P12-KPI-FR-006 | Planned | Import historical KPI scores. |

---

## 4. Domain model

```text
kpis
- id, code, name, description, unit, polarity, legal_entity_id nullable, status

kpi_assignments
- id, kpi_id, assignee_type, assignee_id, weight_percent, target_value,
  period_start, period_end, status

kpi_scores
- id, kpi_assignment_id, actual_value, score_percent, notes, scored_by, scored_at
```

---

## 5. Services & interfaces

```text
KpiService
KpiScoringService
```

---

## 6. Domain events

```text
kpi.scored
```

---

## 7. Configurability surface

* Entire KPI catalogue, weights, targets — data.

---

## 8. Historical migration inputs

* KPI library + historical scores.

---

## 9. Reports / ESS touchpoints

* Scorecards; ESS own KPIs.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-KPI-AC-001 | Planned | Assignment weights for an employee period sum to 100% or warn per policy. |
| P12-KPI-AC-002 | Planned | Appraisal cycle pulls weighted KPI average when linked. |

---

## 11. Out of scope / deferred hooks

* Real-time POS KPI auto-feeds — optional future integrations.
