# Phase 12 — Employees

**Gate / registry key:** `hr`  
**Wave:** 1  
**Depends on:** `hr`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Maintain the employee master record: identity, employment lifecycle, pay structure link, payment details, and branch/entity assignment — the root entity for attendance, leave, payroll, PF, ESS, and talent modules.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `hr.view-employees` | List/view |
| `hr.manage-employees` | Create/update/terminate/reactivate |
| `hr.view-compensation` | View salary structure assignment (Payroll Officer / HR) |
| `selfservice.view-own` | Own profile (read-only subset) |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-EMP-FR-001 | Implemented | The system shall store employee master records with unique `employee_code` per tenant/entity numbering sequence. |
| P12-EMP-FR-002 | Implemented | An employee may optionally link to an auth `user_id` for ESS login. |
| P12-EMP-FR-003 | Implemented | An employee belongs to a `legal_entity_id` and `primary_branch_id`. |
| P12-EMP-FR-004 | Implemented | An employee may be assigned a `salary_structure_id`. |
| P12-EMP-FR-005 | Implemented | Hire date is required; termination date is nullable and set on exit. |
| P12-EMP-FR-006 | Implemented | Employment type is configurable (seeded list; not hard-coded business rules). |
| P12-EMP-FR-007 | Implemented | Default cost centre may be set; used as payroll/expense context when not overridden. |
| P12-EMP-FR-008 | Implemented | Payment method and encrypted bank details may be stored. |
| P12-EMP-FR-009 | Implemented | Employee status supports active/inactive (and equivalent) lifecycle. |
| P12-EMP-FR-010 | Planned | Employee shall support department_id, designation_id, grade_id (FK to org masters). |
| P12-EMP-FR-011 | Planned | Employee shall support reporting_manager_employee_id (see reporting-hierarchy). |
| P12-EMP-FR-012 | Planned | Personal data (name, CNIC/national ID, addresses, contacts, emergency contacts) shall be stored as configurable profile fields / subtables. |
| P12-EMP-FR-013 | Planned | Document attachments (contract, ID copies) use Phase 30 document vault when available; until then configurable disk storage. |
| P12-EMP-FR-014 | Planned | Effective-dated salary structure and org assignments shall be retained historically. |
| P12-EMP-FR-015 | Planned | Probation end date and confirmation date shall be supported fields. |
| P12-EMP-FR-016 | Planned | Multi-branch assignment (secondary branches) shall be supported for attendance/roster. |
| P12-EMP-FR-017 | Planned | Employee import (CSV) shall validate and commit via migration framework; migrated rows marked immutable where historical. |

---

## 4. Domain model

```text
employees                                   # Implemented core
- id
- employee_code                             # DocumentNumberService
- user_id nullable
- legal_entity_id
- primary_branch_id
- salary_structure_id nullable
- hire_date / termination_date nullable
- employment_type
- default_cost_centre_id nullable
- payment_method / bank_details_encrypted nullable
- status
- timestamps

# Planned extensions
- department_id nullable
- designation_id nullable
- grade_id nullable
- reporting_manager_employee_id nullable
- probation_end_date nullable
- confirmation_date nullable
- national_id_encrypted nullable
- display_name / legal_name fields or profile relation

employee_profiles                           # Planned
- employee_id, personal_json, contacts_json, timestamps

employee_branch_assignments                 # Planned
- id, employee_id, branch_id, is_primary, effective_from, effective_to, status

employee_assignment_history                 # Planned effective-dated org/pay assignments
- id, employee_id, field_or_type, old_value, new_value, effective_from, changed_by, timestamps
```

---

## 5. Services & interfaces

```text
EmployeeService           # CRUD, terminate, reactivate
EmployeeCodeService       # wraps DocumentNumberService
EmployeeImportHandler     # Planned — migration framework adapter
```

---

## 6. Domain events

```text
employee.created
employee.updated
employee.terminated
employee.reactivated
employee.salary_structure_changed
```

No GL events from employee master alone.

---

## 7. Configurability surface

* Employment types, payment methods list, required profile fields, numbering sequence, encryption disk/key configuration.

---

## 8. Historical migration inputs

| Data | Required for mid-period go-live |
| :--- | :--- |
| Employee master | Yes |
| Hire / termination dates | Yes |
| Salary structure codes | Yes if payroll |
| Opening leave balances | Via leave migration |
| YTD tax / PF / loans | Via respective modules |

---

## 9. Reports / ESS touchpoints

* Employee directory, headcount, leavers/joiners ([reports.md](./reports.md)).  
* ESS: view own profile; request changes (Planned).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-EMP-AC-001 | Implemented | Creating an employee issues unique `employee_code` via DocumentNumberService. |
| P12-EMP-AC-002 | Implemented | Terminated employee is excluded from subsequent payroll generation by default. |
| P12-EMP-AC-003 | Implemented | Bank details are not stored in plaintext. |
| P12-EMP-AC-004 | Planned | Assigning department/designation/grade persists FKs and appears on employee show. |
| P12-EMP-AC-005 | Planned | Import session commits N employees with reconciliation row count matching file. |
| P12-EMP-AC-006 | Implemented | Employee mutations are audit logged. |

---

## 11. Out of scope / deferred hooks

* Candidate records (pre-hire) — [recruitment-ats.md](./recruitment-ats.md).  
* Onboarding checklists — [onboarding-offboarding.md](./onboarding-offboarding.md).
