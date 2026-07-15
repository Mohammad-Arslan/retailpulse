# Phase 12 — Departments

**Gate / registry key:** `hr`  
**Wave:** 1  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
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
| P12-DEPT-FR-001 | Planned | The system shall support departments with code, name, parent_id (hierarchy), legal_entity_id, status. |
| P12-DEPT-FR-002 | Planned | Department trees shall support arbitrary depth with cycle prevention. |
| P12-DEPT-FR-003 | Planned | Departments may optionally map to a default cost_centre_id. |
| P12-DEPT-FR-004 | Planned | Employees may be assigned to a department (see employees.md). |
| P12-DEPT-FR-005 | Planned | Department assignment shall support effective dating via assignment history. |
| P12-DEPT-FR-006 | Planned | Soft-deactivate preserves history; cannot delete if active employees assigned (configurable force archive). |
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
| P12-DEPT-AC-001 | Planned | Creating a department with parent forming a cycle is rejected. |
| P12-DEPT-AC-002 | Planned | Deactivating a department with active employees is blocked unless policy allows. |
| P12-DEPT-AC-003 | Planned | Duplicate code in same legal entity is rejected. |

---

## 11. Out of scope / deferred hooks

* Matrix / project org structures beyond single parent hierarchy.
