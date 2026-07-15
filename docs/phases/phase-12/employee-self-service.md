# Phase 12 — Employee Self-Service (ESS)

**Gate / registry key:** `employee_self_service`  
**Wave:** 4  
**Depends on:** `hr`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Allow employees (linked users) to view and act on own HR data within permission scope. Full mobile UI is Phase 26; Phase 12 provides services + thin admin ESS.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `selfservice.view-own` | Access own ESS surfaces |

Managers use existing approve permissions, not ESS gate alone.

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-ESS-FR-001 | Partial | View own payslips (service + thin UI). |
| P12-ESS-FR-002 | Partial | View own attendance. |
| P12-ESS-FR-003 | Partial | View leave balance and submit leave requests. |
| P12-ESS-FR-004 | Planned | View own roster, OT, advances, loans, PF, appraisals, reimbursements when those modules enabled. |
| P12-ESS-FR-005 | Planned | Profile self-update for allowed fields with optional approval. |
| P12-ESS-FR-006 | Planned | Full mobile ESS — Phase 26. |
| P12-ESS-FR-007 | Implemented | ESS gate requires `hr`; disabled when module off. |

---

## 4. Domain model

Uses other modules’ tables; optional:

```text
ess_profile_change_requests          # Planned
- id, employee_id, field_changes_json, status, timestamps
```

---

## 5. Services & interfaces

```text
EmployeeSelfServiceFacade            # aggregates read/write for own employee_id only
```

Authorization: always scope to auth user’s linked employee; never expose others.

---

## 6. Domain events

Delegates to leave/reimbursement/etc. events.

---

## 7. Configurability surface

* Which ESS features visible when child modules enabled — gate composition.

---

## 8. Historical migration inputs

* N/A (consumes migrated data).

---

## 9. Reports / ESS touchpoints

* This module *is* the touchpoint.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-ESS-AC-001 | Partial | User cannot view another employee’s payslip via ESS APIs. |
| P12-ESS-AC-002 | Implemented | ESS routes rejected when `employee_self_service` disabled. |
| P12-ESS-AC-003 | Partial | Leave request from ESS creates leave_requests row for linked employee. |

---

## 11. Out of scope / deferred hooks

* Phase 26 React Native employee app.
