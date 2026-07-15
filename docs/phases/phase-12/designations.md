# Phase 12 — Designations

**Gate / registry key:** `hr`  
**Wave:** 1  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Maintain job titles / designations used for hiring, grade banding links, appraisal eligibility, and directory display.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `hr.manage-org` | CRUD designations |
| `hr.view-employees` | Read |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-DES-FR-001 | Planned | The system shall support designations with code, name, legal_entity_id nullable (global or entity-scoped), status. |
| P12-DES-FR-002 | Planned | Designation may optionally link to a default grade_id. |
| P12-DES-FR-003 | Planned | Employees reference designation_id. |
| P12-DES-FR-004 | Planned | Designations are effective-dated when reassigned on employees. |
| P12-DES-FR-005 | Planned | Designations are importable. |

---

## 4. Domain model

```text
designations
- id
- legal_entity_id nullable
- code
- name
- default_grade_id nullable
- status
- timestamps
```

---

## 5. Services & interfaces

```text
DesignationService
```

---

## 6. Domain events

```text
designation.created
designation.updated
```

---

## 7. Configurability surface

* Entire catalogue is data-driven.

---

## 8. Historical migration inputs

* code, name, default_grade_code, entity code.

---

## 9. Reports / ESS touchpoints

* Headcount by designation; job catalogue for ATS.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-DES-AC-001 | Planned | Unique code per entity (or global uniqueness when entity null — configurable). |
| P12-DES-AC-002 | Planned | Employee show displays designation name from FK. |

---

## 11. Out of scope / deferred hooks

* Job descriptions / JD versioning may use document vault later.
