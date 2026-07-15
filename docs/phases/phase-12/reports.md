# Phase 12 — HRMS Reporting & Analytics

**Gate / registry key:** `hrms_reports`  
**Wave:** 4  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

Payroll-specific operational reports: [payroll-reports.md](./payroll-reports.md). Platform report builder: Phase 13.

---

## 1. Objective

Standard HRMS reports and exports across modules; definitions configurable (filters/columns), not hardcoded SQL in controllers.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `hrms_reports.view` | Run |
| `hrms_reports.export` | Export |
| Module view perms | Data scope |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-RPT-FR-001 | Planned | Headcount, joiners, leavers by entity/branch/department/grade. |
| P12-RPT-FR-002 | Planned | Attendance summary / absenteeism. |
| P12-RPT-FR-003 | Planned | Leave balance and utilization by FY. |
| P12-RPT-FR-004 | Planned | OT hours and cost. |
| P12-RPT-FR-005 | Planned | Payroll cost by department/cost centre (reads posted items). |
| P12-RPT-FR-006 | Planned | PF / statutory / tax YTD summaries when modules enabled. |
| P12-RPT-FR-007 | Planned | Appraisal rating distribution. |
| P12-RPT-FR-008 | Planned | ATS time-to-hire / pipeline funnel. |
| P12-RPT-FR-009 | Planned | Queued Excel/PDF export. |
| P12-RPT-FR-010 | Planned | Saved report definitions with role visibility. |
| P12-RPT-FR-011 | Planned | Branch/entity scoping from RBAC + BranchContext. |

---

## 4. Domain model

```text
hrms_report_definitions
- id, code, name, module_scope, query_config_json, status

hrms_report_runs
- id, definition_id, requested_by, status, disk, path, timestamps
```

---

## 5. Services & interfaces

```text
HrmsReportService
HrmsReportExporter
```

---

## 6. Domain events

```text
hrms_report.ready
```

---

## 7. Configurability surface

* Definitions, columns, schedules — config; Phase 13 builder may subsume later.

---

## 8. Historical migration inputs

* Reports read migrated data; no separate import.

---

## 9. Reports / ESS touchpoints

* Manager dashboards limited to team when hierarchy available.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-RPT-AC-001 | Planned | User without branch access cannot export that branch’s headcount. |
| P12-RPT-AC-002 | Planned | Leave utilization report totals match entitlement used_days for FY. |
| P12-RPT-AC-003 | Planned | Disabled module’s reports hidden from catalogue. |

---

## 11. Out of scope / deferred hooks

* Full ad-hoc BI — Phase 13/27.
