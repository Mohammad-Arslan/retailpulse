# Phase 12 — Module Registry

**Purpose:** Single source of truth for sellable gates, dependencies, and roll-up implementation status.  
**Follows:** [architecture.md](./architecture.md)

Status values: `Implemented` | `Partial` | `Planned`.

---

## 1. Sellable modules

| Module | Registry key | Primary SRS file(s) | Depends on | Sellable | Status |
| :--- | :--- | :--- | :--- | :---: | :--- |
| HR Core | `hr` | [hr-core.md](./hr-core.md), [employees.md](./employees.md), [departments.md](./departments.md), [designations.md](./designations.md), [grades.md](./grades.md), [reporting-hierarchy.md](./reporting-hierarchy.md) | — | Yes | Implemented |
| Holiday Calendar | `holiday_calendar` | [holiday-calendar.md](./holiday-calendar.md) | `hr` | Yes | Implemented |
| Attendance | `attendance` | [attendance.md](./attendance.md) | `hr` | Yes | Implemented |
| Shifts & Roster | `shifts_roster` | [shifts-roster.md](./shifts-roster.md) | `hr` | Yes | Planned |
| Leave | `leave` | [leave.md](./leave.md), [leave-policies.md](./leave-policies.md), [leave-fiscal-year.md](./leave-fiscal-year.md) | `hr` | Yes | Partial |
| Overtime | `overtime` | [overtime.md](./overtime.md) | `hr`, `attendance` | Yes | Implemented |
| Payroll Core | `payroll` | [payroll-core.md](./payroll-core.md), [payroll-scheduling.md](./payroll-scheduling.md), [payroll-reports.md](./payroll-reports.md), [deductions.md](./deductions.md) | `hr` | Yes | Partial |
| Tax Engine | `tax_engine` | [tax-engine.md](./tax-engine.md) | `payroll` | Yes | Partial |
| Statutory | `statutory` | [statutory.md](./statutory.md) | `payroll` | Yes | Partial |
| Provident Fund | `provident_fund` | [provident-fund.md](./provident-fund.md) | `payroll` | Yes | Planned |
| Salary Advance | `salary_advance` | [salary-advance.md](./salary-advance.md) | `payroll` | Yes | Partial |
| Employee Loans | `employee_loans` | [employee-loans.md](./employee-loans.md) | `payroll` | Yes | Planned |
| Payroll Adjustments | `payroll_adjustments` | [payroll-adjustments.md](./payroll-adjustments.md) | `payroll` | Yes | Planned |
| Expenses | `expenses` | [expenses.md](./expenses.md) | — | Yes | Implemented |
| Reimbursements | `reimbursements` | [reimbursements.md](./reimbursements.md) | `hr` | Yes | Planned |
| Appraisal Suite | `appraisal` | [kpi.md](./kpi.md), [kra.md](./kra.md), [appraisal.md](./appraisal.md), [compensation.md](./compensation.md) | `hr` | Yes | Planned |
| Recruitment (ATS) | `recruitment` | [recruitment-ats.md](./recruitment-ats.md) | `hr` | Yes | Planned |
| Onboarding | `onboarding` | [onboarding-offboarding.md](./onboarding-offboarding.md) | `hr` | Yes | Planned |
| Employee Assets | `employee_assets` | [asset-management.md](./asset-management.md) | `hr` | Yes | Planned |
| Employee Self-Service | `employee_self_service` | [employee-self-service.md](./employee-self-service.md) | `hr` | Yes | Partial |
| HRMS Reports | `hrms_reports` | [reports.md](./reports.md) | `hr` | Yes | Planned |

Cross-cutting (not separately licensed gates, but required reading):

| Area | File | Status |
| :--- | :--- | :--- |
| Architecture | [architecture.md](./architecture.md) | Binding |
| Historical Migration | [historical-migration.md](./historical-migration.md) | Partial |
| Configuration Framework | [configuration-framework.md](./configuration-framework.md) | Partial |

---

## 2. Config file mapping

Current implementation uses `config/hr_payroll_modules.php` (mirrors accounting modules). Extend keys as gates above are shipped. Replace gate binding later with Phase 23 registry without changing module services.

Baseline keys already in production foundation:

```text
expenses, hr, attendance, leave, overtime, payroll, employee_self_service, holiday_calendar
```

---

## 3. Permission namespaces (index)

| Gate | Permission prefix examples |
| :--- | :--- |
| `expenses` | `expenses.view`, `create`, `approve`, `post`, `reverse`, `manage-categories`, `manage-recurring` |
| `hr` | `hr.view-employees`, `manage-employees`, `manage-org` |
| `attendance` | `attendance.view`, `record`, `adjust`, `manage-sources` |
| `leave` | `leave.view`, `request`, `approve`, `manage-types`, `manage-policies` |
| `overtime` | `overtime.view`, `approve`, `manage-policies` |
| `payroll` | `payroll.view`, `process`, `approve`, `post`, `reverse`, `manage-components`, `manage-structures` |
| `tax_engine` / statutory | `payroll.manage-tax-slabs`, `payroll.manage-statutory` |
| `provident_fund` | `pf.view`, `manage`, `approve-withdrawal`, `post` |
| `salary_advance` / loans | `advances.view`, `issue`, `recover`; `loans.view`, `issue`, `recover` |
| `appraisal` | `appraisal.view`, `manage-kpi`, `manage-kra`, `conduct`, `approve` |
| `recruitment` | `recruitment.view`, `manage-jobs`, `manage-candidates` |
| `employee_self_service` | `selfservice.view-own` |

Full permission lists live in each module SRS §2.

---

## 4. Accounting events ownership

| Event | Owning module SRS |
| :--- | :--- |
| `expense.*` | [expenses.md](./expenses.md) |
| `reimbursement.*` | [reimbursements.md](./reimbursements.md) |
| `payroll.*` | [payroll-core.md](./payroll-core.md) |
| `employee_advance.*` | [salary-advance.md](./salary-advance.md) |
| `employee_loan.*` | [employee-loans.md](./employee-loans.md) |
| `provident_fund.*` | [provident-fund.md](./provident-fund.md) |
| `employee_asset.*` | [asset-management.md](./asset-management.md) |

Event catalogue master: [architecture.md](./architecture.md) §5.
