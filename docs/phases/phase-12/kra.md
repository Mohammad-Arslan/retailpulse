# Phase 12 — KRA Management

**Gate / registry key:** `appraisal`  
**Wave:** 4  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Key Result Areas: qualitative/outcome objectives distinct from numeric KPIs, linked to appraisal cycles.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `appraisal.manage-kra` | CRUD |
| `appraisal.view` | View |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-KRA-FR-001 | Planned | KRA templates and employee KRAs with description, success criteria, weight, period. |
| P12-KRA-FR-002 | Planned | Progress updates and manager comments. |
| P12-KRA-FR-003 | Planned | Rating scale configurable (e.g. 1–5 labels) — not hardcoded. |
| P12-KRA-FR-004 | Planned | Roll-up into appraisal overall score with configurable KPI:KRA blend %. |
| P12-KRA-FR-005 | Planned | Historical KRA import. |

---

## 4. Domain model

```text
kra_templates
- id, code, name, designation_id nullable, status

employee_kras
- id, employee_id, template_id nullable, title, success_criteria,
  weight_percent, period_start, period_end, status

kra_progress_entries
- id, employee_kra_id, entry_date, progress_notes, rating nullable, author_id
```

---

## 5. Services & interfaces

```text
KraService
```

---

## 6. Domain events

```text
kra.updated
kra.rated
```

---

## 7. Configurability surface

* Rating scales, weights, blend with KPI — config.

---

## 8. Historical migration inputs

* Prior cycle KRAs/ratings.

---

## 9. Reports / ESS touchpoints

* ESS maintain own KRA progress.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-KRA-AC-001 | Planned | Rating must use configured scale values only. |
| P12-KRA-AC-002 | Planned | Changing scale labels does not require code deploy. |

---

## 11. Out of scope / deferred hooks

* OKR nested objective trees beyond single-level KRA — optional later.
