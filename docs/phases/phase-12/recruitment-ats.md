# Phase 12 — Recruitment (ATS)

**Gate / registry key:** `recruitment`  
**Wave:** 4  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Applicant Tracking System: job requisitions, candidates, pipeline stages, offers — configurable stages and fields; hire creates/links employee via onboarding.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `recruitment.view` / `manage-jobs` / `manage-candidates` / `manage-offers` | ATS |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-ATS-FR-001 | Planned | Job requisitions: title, department, designation, grade, headcount, status, approval. |
| P12-ATS-FR-002 | Planned | Configurable pipeline stages per entity (e.g. applied→screen→interview→offer→hired). |
| P12-ATS-FR-003 | Planned | Candidates with resume attachments (configurable disk / vault). |
| P12-ATS-FR-004 | Planned | Applications link candidate↔requisition with stage history. |
| P12-ATS-FR-005 | Planned | Interview feedback forms configurable. |
| P12-ATS-FR-006 | Planned | Offers: compensation components snapshot, expiry, accept/reject. |
| P12-ATS-FR-007 | Planned | On accept, create employee draft + onboarding checklist (link to onboarding module). |
| P12-ATS-FR-008 | Planned | Duplicate candidate detection by email/phone/national_id (config). |
| P12-ATS-FR-009 | Planned | Candidate/requisition import. |

---

## 4. Domain model

```text
job_requisitions
- id, code, title, department_id, designation_id, grade_id nullable,
  openings, status, legal_entity_id, branch_id nullable, timestamps

ats_pipeline_stages
- id, legal_entity_id, code, name, sequence, is_terminal, status

candidates
- id, name, email, phone, national_id_encrypted nullable, source, status

applications
- id, job_requisition_id, candidate_id, stage_id, applied_at, status

application_stage_history
- id, application_id, from_stage_id, to_stage_id, changed_by, changed_at, notes

interview_feedback
- id, application_id, interviewer_id, scores_json, recommendation, timestamps

offers
- id, application_id, offer_number, package_json, expires_on, status
```

---

## 5. Services & interfaces

```text
RecruitmentService
OfferService
HireToEmployeeBridge
```

---

## 6. Domain events

```text
requisition.opened
application.stage_changed
offer.accepted
candidate.hired
```

No GL.

---

## 7. Configurability surface

* Stages, forms, offer fields — config.

---

## 8. Historical migration inputs

* Open requisitions/candidates optional.

---

## 9. Reports / ESS touchpoints

* Time-to-hire; hiring manager limited portal (Planned).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-ATS-AC-001 | Planned | Stage transitions only along configured pipeline. |
| P12-ATS-AC-002 | Planned | Accepted offer can create employee with hire_date and draft onboarding. |
| P12-ATS-AC-003 | Planned | Pipeline stage labels changeable without code deploy. |

---

## 11. Out of scope / deferred hooks

* Public careers site — Phase 25/15; ATS admin is internal first.
