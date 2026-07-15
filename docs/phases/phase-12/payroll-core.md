# Phase 12 — Payroll Core

**Gate / registry key:** `payroll`  
**Wave:** 3  
**Depends on:** `hr`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

Related: [payroll-scheduling.md](./payroll-scheduling.md), [deductions.md](./deductions.md), [tax-engine.md](./tax-engine.md), [statutory.md](./statutory.md), [payroll-adjustments.md](./payroll-adjustments.md).

---

## 1. Objective

Run payroll as a **rule engine over pay components** (not a fixed salary formula): structures, calculation, draft→approve→post→reverse lifecycle, payslips, and GL only via `payroll.posted` / `payroll.reversed`.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `payroll.view` | View runs/items |
| `payroll.process` | Generate draft |
| `payroll.approve` | Approve |
| `payroll.post` | Post (GL event) |
| `payroll.reverse` | Reverse |
| `payroll.manage-components` | Pay components |
| `payroll.manage-structures` | Salary structures |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-PAY-FR-001 | Implemented | Pay components support types: earning / deduction / employer_contribution / statutory / reimbursement. |
| P12-PAY-FR-002 | Partial | calculation_type: fixed / percentage_of / formula / table_lookup — formula evaluator deferred (rejected safely). |
| P12-PAY-FR-003 | Implemented | Components have taxable flag, account_mapping_key, effective dating, entity scope. |
| P12-PAY-FR-004 | Implemented | Salary structures aggregate components with sequence and amount_or_rate. |
| P12-PAY-FR-005 | Implemented | Payroll run stores period, entity, currency, status draft/pending_approval/approved/posted/reversed. |
| P12-PAY-FR-006 | Implemented | Run generation creates draft items/lines from structure + OT + leave + statutory + tax. |
| P12-PAY-FR-007 | Implemented | `snapshot_json` freezes resolved rates; later config never alters posted runs. |
| P12-PAY-FR-008 | Implemented | Post publishes `payroll.posted` once (idempotent); never writes journals directly. |
| P12-PAY-FR-009 | Implemented | Reverse publishes `payroll.reversed` → Phase 11 reversal journal. |
| P12-PAY-FR-010 | Implemented | Approval settings per entity; Phase 29 workflow hook stubbed. |
| P12-PAY-FR-011 | Implemented | Payslip PDF totals match payroll_item lines; storage disk configurable. |
| P12-PAY-FR-012 | Implemented | Email/bulk send on confirmation; channel configurable (degrade to queued mail). |
| P12-PAY-FR-013 | Implemented | Payroll numbers via DocumentNumberService. |
| P12-PAY-FR-014 | Implemented | Services must not import JournalService / PostingRuleEngine for writes. |
| P12-PAY-FR-015 | Planned | Mid-period joiners/exiters prorate earnings per component config. |
| P12-PAY-FR-016 | Planned | Off-cycle / ad-hoc runs (see payroll-scheduling). |

---

## 4. Domain model

```text
pay_components
- id, code, name, type, calculation_type, basis_component_id nullable,
  rate nullable, formula_expression nullable, taxable,
  account_mapping_key, effective_from / effective_to nullable,
  legal_entity_id nullable, status

salary_structures
- id, code, name, legal_entity_id nullable, status

salary_structure_components
- id, salary_structure_id, pay_component_id, amount_or_rate nullable, sequence

payroll_runs
- id, payroll_number, legal_entity_id, branch_id nullable,
  period_start, period_end, currency_code,
  status, totals_json, accounting_event_id nullable, journal_entry_id nullable,
  approved_by nullable, posted_by nullable, timestamps

payroll_items
- id, payroll_run_id, employee_id, gross, total_deductions,
  total_employer_contributions, net_pay, ytd_json, snapshot_json, timestamps

payroll_item_lines
- id, payroll_item_id, pay_component_id, component_snapshot_json, amount, sequence

payroll_approval_settings
- id, legal_entity_id, requires_approval, approval_limit nullable,
  use_workflow_engine
```

---

## 5. Services & interfaces

```text
PayrollCalculationService
PayrollRunService
PayslipService
# Never import JournalService for write path
```

---

## 6. Domain events

```text
payroll.posted
payroll.approved
payroll.reversed
```

Idempotency: `payroll.posted:PayrollRun:{id}`

---

## 7. Configurability surface

* Components, structures, approval, numbering, mapping keys — all config.

---

## 8. Historical migration inputs

* Historical payslips / payroll items as immutable migrated rows; YTD openings via tax-engine / migration doc.

---

## 9. Reports / ESS touchpoints

* [payroll-reports.md](./payroll-reports.md); ESS payslips.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-PAY-AC-001 | Implemented | No Phase 12 payroll service references JournalService for posting. |
| P12-PAY-AC-002 | Implemented | Posted run creates balanced journal via posting rules (net salary, tax, statutory). |
| P12-PAY-AC-003 | Implemented | Reprocess same run → no duplicate journal (idempotency). |
| P12-PAY-AC-004 | Implemented | Payslip PDF totals = item lines. |
| P12-PAY-AC-005 | Implemented | Reversal creates linked Phase 11 reversal; no manual GL edit. |
| P12-PAY-AC-006 | Partial | formula component rejected until sandbox parser ships. |
| P12-PAY-AC-007 | Implemented | Branch with payroll disabled rejects payroll routes. |

---

## 11. Out of scope / deferred hooks

* Formula sandbox parser (P12-05).  
* Phase 29 workflow engine.
