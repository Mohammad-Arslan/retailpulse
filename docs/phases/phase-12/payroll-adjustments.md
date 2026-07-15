# Phase 12 — Payroll Adjustments

**Gate / registry key:** `payroll_adjustments`  
**Wave:** 3  
**Depends on:** `payroll`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Support arrears, bonuses, incentives, corrections, and recoveries as first-class, configurable adjustment documents that feed payroll runs without ad-hoc manual GL edits.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `payroll.adjustments.view` | View |
| `payroll.adjustments.manage` | Create/edit |
| `payroll.adjustments.approve` | Approve |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-PADJ-FR-001 | Planned | Adjustment types (config catalogue): arrears, bonus, incentive, correction, recovery, other. |
| P12-PADJ-FR-002 | Planned | Each adjustment links employee, pay_component (or type→default component), amount, reason, period, effective payroll period. |
| P12-PADJ-FR-003 | Planned | Arrears may span multiple prior periods with line breakdown. |
| P12-PADJ-FR-004 | Planned | Bonuses/incentives may be taxable per component flag; tax engine consumes when included in run. |
| P12-PADJ-FR-005 | Planned | Recoveries create deduction lines; may link to advances/loans. |
| P12-PADJ-FR-006 | Planned | Corrections to posted payroll must not edit posted snapshots; use adjustment in future run or reversal+rerun policy. |
| P12-PADJ-FR-007 | Planned | Approval policy configurable; audit logged. |
| P12-PADJ-FR-008 | Planned | Adjustments importable (historical bonuses/arrears). |
| P12-PADJ-FR-009 | Planned | Inclusion in a payroll run freezes adjustment into item lines; status → applied. |

---

## 4. Domain model

```text
payroll_adjustment_types
- id, code, name, default_pay_component_id nullable, direction (earning|deduction), status

payroll_adjustments
- id, adjustment_number, employee_id, adjustment_type_id, pay_component_id,
  amount, currency_code, reason, target_period_start, target_period_end,
  status (draft|pending_approval|approved|applied|cancelled),
  payroll_run_id nullable, approved_by nullable, timestamps

payroll_adjustment_lines                    # for arrears breakdown
- id, payroll_adjustment_id, period_key, amount, notes
```

---

## 5. Services & interfaces

```text
PayrollAdjustmentService
PayrollCalculationService.applyAdjustments(run)
```

---

## 6. Domain events

```text
payroll_adjustment.approved
payroll_adjustment.applied
```

GL via parent `payroll.posted` when applied in a run (unless one-off post policy configured later).

---

## 7. Configurability surface

* Types, default components, approval — config.

---

## 8. Historical migration inputs

* Open arrears, historical bonuses with applied flag.

---

## 9. Reports / ESS touchpoints

* Adjustment register; ESS may show applied lines on payslip.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-PADJ-AC-001 | Planned | Approved bonus appears on next run as component line. |
| P12-PADJ-AC-002 | Planned | Posted payroll item snapshot unchanged when later bonus created for another period. |
| P12-PADJ-AC-003 | Planned | Applied adjustment cannot be double-applied (status guard + unique). |

---

## 11. Out of scope / deferred hooks

* Stock options / equity — out of scope.
