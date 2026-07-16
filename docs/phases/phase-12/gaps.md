# Phase 12 — Gaps (Partial & Planned)

**Source of truth for unfinished FRs.** Implemented items are not listed.  
**Registry:** [module-registry.md](./module-registry.md)  
**Also summarized in:** [docs/gaps/gaps.md](../../gaps/gaps.md) (P12-01…P12-08)

Status legend: **Partial** = some code exists / incomplete vs SRS · **Planned** = not built.

---

## Cross-cutting

| ID | Severity | Status | Gap |
| :--- | :---: | :--- | :--- |
| P12-05 / P12-CFG-FR-005 | Low | Partial | Sandboxed formula evaluator for pay components |
| P12-06 | Low | Partial | Phase 29 workflow engine hooks (expense/payroll/leave approvals) |
| P12-MIG-* | High | Partial | Full historical migration suite (only tax YTD openings substantive today) |
| P12-CFG-FR-003 | Medium | Planned | Country/jurisdiction config packs |
| P12-CFG-FR-008 | Medium | Planned | Notification templates per HRMS event |
| P12-CFG-FR-012 | Medium | Planned | HRMS Config Centre UI (Phase 23 alignment) |

---

## Wave 1 — HR foundation

| Area | Status | Notable Planned / Partial FRs |
| :--- | :--- | :--- |
| HR Core | Implemented | Employment-type catalogue, entity HR settings |
| Employees | Implemented | Profile subtables, multi-branch, org FKs, import/export, effective-dated org assignments; Phase 30 vault deferred |
| Departments | Implemented | Import/export via generic wizard |
| Designations | Implemented | Import/export via generic wizard |
| Grades | Implemented | Import/export; band enforcement on payroll Pending (Wave 3) |
| Reporting hierarchy | Partial | Manager + cycle + history + org chart + delegations + import Implemented; Phase 29 workflow strategies Planned |
| Holiday calendar | Implemented | Leave/OT consumption Implemented; roster holiday marking Wave 2 |

---

## Wave 2 — Time & leave

| Area | Status | Notable gaps |
| :--- | :--- | :--- |
| Attendance | Implemented | Late/early vs roster, policies, historical import (Planned) |
| Shifts & roster | Planned | Entire module |
| Leave | Partial | Holiday-aware day count, half-day, encashment |
| Leave policies | Partial | Encashment, eligibility JSON, accrual scheduler hardening |
| Leave fiscal year | Partial | Year-end carry/expire/encash job |
| Overtime | Implemented | Roster/holiday integration, comp-off (Planned) |

---

## Wave 3 — Compensation & expenses

| Area | Status | Notable gaps |
| :--- | :--- | :--- |
| Payroll core | Partial | Formula calc, mid-period proration depth |
| Payroll scheduling | Planned | Calendars, auto-drafts, off-cycle |
| Payroll adjustments | Planned | Arrears, bonus, incentives, corrections, recoveries |
| Payroll reports | Partial | Register, bank export, YTD, variance |
| Deductions | Partial | Recurring assignments, shortfall policy, garnishments |
| Statutory | Partial | Pluggable calculators, assignments, YTD openings |
| Tax engine | Partial | Full UI parity + all methods seeded; ACs specified |
| Provident fund | Planned | Entire module |
| Salary advance | Partial | Policy engine, ESS, auto-recovery schedules |
| Employee loans | Planned | Entire module |
| Expenses | Implemented | Multi-line cost split, historical import (Planned) |
| Reimbursements | Planned | Entire employee-claims module |

---

## Wave 4 — Talent, ESS, ops

| Area | Status | Notable gaps |
| :--- | :--- | :--- |
| KPI / KRA / Appraisal / Compensation | Planned | Entire appraisal suite |
| ESS | Partial | Thin services; full feature matrix + Phase 26 mobile |
| Recruitment ATS | Planned | Entire module |
| Onboarding / offboarding | Planned | Entire module |
| Employee assets | Planned | Entire module |
| HRMS reports | Planned | Catalogue + exports |
| Historical migration | Partial | Framework + handlers beyond tax YTD |
| Configuration framework | Partial | Packs, resolver centralization, notification templates |

---

## Foundation residuals (still tracked)

| ID | Severity | Notes |
| :--- | :---: | :--- |
| P12-05 | Low | Formula evaluator |
| P12-06 | Low | Workflow engine |
| P12-07 | Low | Full employee mobile ESS UI → Phase 26 |
| P12-08 | High | Enterprise expansion specified in this folder |

---

## Consistency checklist (authoring gate)

- [x] Accounting events listed in [architecture.md](./architecture.md) §5 match module ownership in [module-registry.md](./module-registry.md)
- [x] Mapping keys baseline in architecture §5.4; PF/loan/asset keys documented
- [x] Wave order in [README.md](./README.md) matches registry dependencies
- [x] Legacy pointer [../phase-12-expenses-hr-payroll.md](../phase-12-expenses-hr-payroll.md) → this folder
- [x] User manual + phases README links updated
