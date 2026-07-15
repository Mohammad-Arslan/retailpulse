# Phase 12 — Payroll Reports

**Gate / registry key:** `payroll` (reporting also via `hrms_reports`)  
**Wave:** 3  
**Depends on:** `payroll`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Deliver standard payroll operational reports and exports; advanced analytics defer to Phase 13 / [reports.md](./reports.md).

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `payroll.view` | Run reports |
| `payroll.export` | Export (Planned / extend sales.export pattern) |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-PRPT-FR-001 | Partial | Payslip PDF per item (Implemented). |
| P12-PRPT-FR-002 | Planned | Payroll register (employees × components) for a run. |
| P12-PRPT-FR-003 | Planned | Bank transfer / payment file export (configurable template; no hardcoded bank format beyond pluggable exporters). |
| P12-PRPT-FR-004 | Planned | Department/cost-centre cost summary. |
| P12-PRPT-FR-005 | Planned | YTD earnings/tax/deductions per employee. |
| P12-PRPT-FR-006 | Planned | Variance vs prior period. |
| P12-PRPT-FR-007 | Planned | Queued Excel/PDF export. |

---

## 4. Domain model

Uses payroll_runs / items / lines. Optional:

```text
payroll_report_definitions       # Planned — saved filters
payroll_export_jobs              # Planned
```

---

## 5. Services & interfaces

```text
PayslipService                   # Implemented
PayrollRegisterReportService     # Planned
PayrollBankExportProvider        # Planned interface
```

---

## 6. Domain events

None required.

---

## 7. Configurability surface

* Export templates, columns, bank formats — provider + config.

---

## 8. Historical migration inputs

* Historical payslips for YTD reports.

---

## 9. Reports / ESS touchpoints

* ESS: own payslips only.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-PRPT-AC-001 | Implemented | Payslip totals match item lines. |
| P12-PRPT-AC-002 | Planned | Bank export sums equal net_pay totals for selected run. |
| P12-PRPT-AC-003 | Planned | YTD report = openings + posted items for FY. |

---

## 11. Out of scope / deferred hooks

* BI dashboards — Phase 27 / [reports.md](./reports.md).
