# Phase 12 — Reporting Hierarchy

**Gate / registry key:** `hr`  
**Wave:** 1  
**Depends on:** `hr`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Define who reports to whom for leave/expense/overtime/appraisal approvals and org chart views — fully configurable, not hardcoded manager roles.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `hr.manage-org` | Maintain hierarchy |
| `hr.view-employees` | View org chart |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-RH-FR-001 | Implemented | Each employee may have a `reporting_manager_employee_id` (direct manager). |
| P12-RH-FR-002 | Implemented | Manager assignment shall be effective-dated; concurrent history stored. |
| P12-RH-FR-003 | Implemented | Hierarchy shall prevent cycles (employee cannot report to self or descendant). |
| P12-RH-FR-004 | Planned | Approval policies may resolve approver as: direct_manager | department_head | role | named_user | workflow (Phase 29). |
| P12-RH-FR-005 | Planned | Org chart API/UI shall return tree from a root or whole entity. |
| P12-RH-FR-006 | Planned | Temporary delegation (acting manager) with date range shall be supported for approvals. |
| P12-RH-FR-007 | Planned | Hierarchy import supported (employee_code → manager_employee_code). |

---

## 4. Domain model

```text
# Primary link on employees.reporting_manager_employee_id (Planned)

employee_manager_history
- id, employee_id, manager_employee_id, effective_from, effective_to nullable,
  changed_by, timestamps

approval_delegations
- id, from_employee_id, to_employee_id, scope (leave|expense|overtime|all),
  effective_from, effective_to, status, timestamps
```

---

## 5. Services & interfaces

```text
ReportingHierarchyService
  resolveManager(employee, date)
  assertNoCycle(...)
  orgChart(legalEntityId)
ApprovalApproverResolver          # uses hierarchy + policies
```

---

## 6. Domain events

```text
employee.manager_changed
approval_delegation.created
```

---

## 7. Configurability surface

* Approver resolution strategies and delegation scopes — config; no hardcoded “always branch manager”.

---

## 8. Historical migration inputs

* employee_code, manager_employee_code, effective_from.

---

## 9. Reports / ESS touchpoints

* Org chart; span-of-control report.  
* ESS: view own manager (Planned).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-RH-AC-001 | Implemented | Assigning a manager that creates a cycle is rejected. |
| P12-RH-AC-002 | Planned | Leave approval with strategy `direct_manager` routes to resolved manager at request date. |
| P12-RH-AC-003 | Planned | Active delegation redirects approvals within date range. |

---

## 11. Out of scope / deferred hooks

* Matrix reporting (multiple dotted-line managers) — optional later; MVP is single solid line.
