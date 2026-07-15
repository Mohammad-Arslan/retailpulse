# Phase 12 — Departments

**Gate / registry key:** `hr`  
**Wave:** 1  
**Depends on:** `hr`  
**Status (module roll-up):** Implemented  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Provide hierarchical department (org unit) masters per legal entity for reporting, cost allocation context, approval routing, and analytics.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `hr.manage-org` | CRUD departments |
| `hr.view-employees` | Read departments for filters |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-DEPT-FR-001 | Implemented | The system shall support departments with auto-generated unique code, name, parent_id (hierarchy), legal_entity_id, status. |
| P12-DEPT-FR-002 | Implemented | Department trees shall support arbitrary depth with cycle prevention. |
| P12-DEPT-FR-003 | Implemented | Departments may optionally map to a default cost_centre_id. |
| P12-DEPT-FR-004 | Implemented | Employees may be assigned to a department (see employees.md). |
| P12-DEPT-FR-005 | Partial | Department assignment shall support effective dating via assignment history. |
| P12-DEPT-FR-006 | Implemented | Soft-deactivate preserves history; cannot deactivate if active employees assigned. |
| P12-DEPT-FR-007 | Planned | Departments are importable via historical migration. |

---

## 4. Domain model

```text
departments
- id
- legal_entity_id
- code
- name
- parent_id nullable
- cost_centre_id nullable
- status
- timestamps
```

Unique: `(legal_entity_id, code)`.

---

## 5. Services & interfaces

```text
DepartmentService
  create / update / deactivate / tree / assignEmployee
```

---

## 6. Domain events

```text
department.created
department.updated
department.deactivated
```

---

## 7. Configurability surface

* Codes, hierarchy, cost-centre linkage — all data; no hardcoded org chart.

---

## 8. Historical migration inputs

* Department code, name, parent code, cost centre code, status.

---

## 9. Reports / ESS touchpoints

* Headcount by department; org chart export ([reports.md](./reports.md)).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-DEPT-AC-001 | Implemented | Creating a department with parent forming a cycle is rejected. |
| P12-DEPT-AC-002 | Implemented | Deactivating a department with active employees is blocked. |
| P12-DEPT-AC-003 | Implemented | Department codes are peeked on create (`DEPT-#####`), allocated uniquely on save, and read-only in the UI. |

---

## 11. Out of scope / deferred hooks

* Matrix / project org structures beyond single parent hierarchy.
