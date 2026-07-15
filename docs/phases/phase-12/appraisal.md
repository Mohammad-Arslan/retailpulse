# Phase 12 — Appraisal Cycles

**Gate / registry key:** `appraisal`  
**Wave:** 4  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

Uses [kpi.md](./kpi.md) and [kra.md](./kra.md). Compensation outcomes: [compensation.md](./compensation.md).

---

## 1. Objective

Configurable performance appraisal cycles: self/manager/peer review stages, scoring, calibration, and outcomes — no hardcoded rating bands.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `appraisal.conduct` | Fill appraisals |
| `appraisal.approve` | Calibrate / finalize |
| `appraisal.manage` | Define cycles |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-APP-FR-001 | Planned | Appraisal cycles: name, period, stages (self, manager, skip-level, hr), due dates. |
| P12-APP-FR-002 | Planned | Participants generated from eligibility rules (entity, grade, employment_type). |
| P12-APP-FR-003 | Planned | Overall score formula configurable (KPI/KRA blend + qualitative). |
| P12-APP-FR-004 | Planned | Rating bands configurable (labels + min/max score). |
| P12-APP-FR-005 | Planned | Calibration session adjust scores with audit trail. |
| P12-APP-FR-006 | Planned | Outcomes status: draft → submitted → reviewed → finalized. |
| P12-APP-FR-007 | Planned | Link to compensation recommendation (optional). |
| P12-APP-FR-008 | Planned | Historical appraisals importable (immutable). |
| P12-APP-FR-009 | Planned | ESS self-review and view finalized scores. |

---

## 4. Domain model

```text
appraisal_cycles
- id, code, name, legal_entity_id, period_start, period_end,
  stages_json, kpi_weight_percent, kra_weight_percent, status

appraisal_rating_scales
- id, cycle_id nullable, code, label, min_score, max_score

appraisal_participants
- id, cycle_id, employee_id, manager_employee_id, status

appraisal_forms
- id, participant_id, stage, scores_json, comments, submitted_at

appraisal_final_results
- id, participant_id, overall_score, rating_code, calibrated_score nullable,
  finalized_at, finalized_by
```

---

## 5. Services & interfaces

```text
AppraisalCycleService
AppraisalScoringService
AppraisalCalibrationService
```

---

## 6. Domain events

```text
appraisal.cycle_opened
appraisal.submitted
appraisal.finalized
```

No GL.

---

## 7. Configurability surface

* Stages, weights, rating bands, eligibility — config.

---

## 8. Historical migration inputs

* Finalized appraisals and ratings.

---

## 9. Reports / ESS touchpoints

* Distribution ratings; ESS self/manager forms.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-APP-AC-001 | Planned | Finalized appraisal cannot be silently edited; correction = new adjustment with audit. |
| P12-APP-AC-002 | Planned | Score uses configured KPI/KRA weights. |
| P12-APP-AC-003 | Planned | Employee without manager blocked if stage requires manager and hierarchy missing. |

---

## 11. Out of scope / deferred hooks

* 360 peer selection UI complexity — stages configurable; peer lists MVP optional.
