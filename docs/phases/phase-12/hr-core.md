# Phase 12 — HR Core

**Gate / registry key:** `hr`  
**Wave:** 1  
**Depends on:** —  
**Status (module roll-up):** Implemented  
**Follows:** [architecture.md](./architecture.md)

Companion specs: [employees.md](./employees.md), [departments.md](./departments.md), [designations.md](./designations.md), [grades.md](./grades.md), [reporting-hierarchy.md](./reporting-hierarchy.md).

---

## 1. Objective

Provide the sellable HR foundation gate: org structure enablement, shared HR settings, employment type catalogues, cost-centre defaults, and the service boundary consumed by all Wave 2–4 modules.

---

## 2. Actors & permissions

| Permission | Actors |
| :--- | :--- |
| `hr.view-employees` | HR Manager, Line Manager (scoped), Payroll Officer |
| `hr.manage-employees` | HR Manager |
| `hr.manage-org` | HR Manager |
| `hr.manage-settings` | HR Manager, Super Admin |

Default roles seeded and editable (Phase 1 Spatie).

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-HRCORE-FR-001 | Implemented | The system shall expose an independently gateable `hr` module per branch. |
| P12-HRCORE-FR-002 | Implemented | When `hr` is disabled, all dependent module routes and nav items shall be rejected/hidden. |
| P12-HRCORE-FR-003 | Implemented | The system shall maintain configurable employment-type catalogues per legal entity (seeded baseline: full_time, part_time, contract, hourly — fully editable; not a fixed enum in business logic). |
| P12-HRCORE-FR-004 | Implemented | The system shall support HR settings per legal entity: default fiscal year for leave, default holiday calendar, default cost centre policy, and document number sequences for employee codes. |
| P12-HRCORE-FR-005 | Implemented | Employee codes shall be issued via Phase 11 `DocumentNumberService`. |
| P12-HRCORE-FR-006 | Implemented | The system shall support org units (departments), designations, grades, and reporting hierarchy as first-class masters (see companion docs). |
| P12-HRCORE-FR-007 | Implemented | All HR create/update/deactivate actions shall be audit logged. |
| P12-HRCORE-FR-008 | Implemented | The system shall support effective-dated assignments of department, designation, grade, branch, and manager (history preserved). |
| P12-HRCORE-FR-009 | Planned | Historical employee/org import shall use the migration framework ([historical-migration.md](./historical-migration.md)). |

---

## 4. Domain model

```text
hr_employment_types                 # Implemented
- id, legal_entity_id nullable, code, name, status, timestamps

hr_entity_settings                  # Implemented
- id, legal_entity_id
- default_leave_fiscal_year_mode
- default_holiday_calendar_id nullable
- employee_code_sequence_key
- settings_json
- timestamps

branch_hr_profiles                  # Implemented (module gates)
- id, branch_id, hr_enabled_modules (json/array), timestamps
```

Org masters: see departments / designations / grades / reporting-hierarchy docs.

---

## 5. Services & interfaces

```text
HrModuleGate / EnsureModuleEnabled   # Implemented pattern
EmployeeService                      # see employees.md
OrgStructureService                  # Implemented via Department/Designation/Grade services
```

---

## 6. Domain events

None for GL. Optional domain events for notifications:

```text
employee.created
employee.terminated
org_assignment.changed
```

---

## 7. Configurability surface

* Employment types, HR entity defaults, numbering sequences, module gates — never hardcoded per tenant.

---

## 8. Historical migration inputs

* Org masters (departments, designations, grades)  
* Employment types  
* See [employees.md](./employees.md) for employee rows

---

## 9. Reports / ESS touchpoints

* Headcount by entity/branch/department (requires org masters — Planned in [reports.md](./reports.md)).  
* ESS: none directly (employee profile via ESS).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-HRCORE-AC-001 | Implemented | Branch with `hr` disabled rejects employee routes and hides HR nav. |
| P12-HRCORE-AC-002 | Implemented | Enabling `attendance` without `hr` is rejected by dependency graph. |
| P12-HRCORE-AC-003 | Implemented | Changing employment-type catalogue labels updates UI without deployment. |
| P12-HRCORE-AC-004 | Implemented | Effective-dated department reassignment preserves prior assignment history rows. |

---

## 11. Out of scope / deferred hooks

* Full mobile HR admin — Phase 26.  
* Workflow for org changes — Phase 29 optional.
