# Phase 12 — Employee Loans

**Gate / registry key:** `employee_loans`  
**Wave:** 3  
**Depends on:** `payroll`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

Distinct from salary advances: multi-instalment amortizing loans with interest policy options.

---

## 1. Objective

Configurable employee loans: disbursement, amortization, payroll recovery, early settlement, write-off — GL via events only.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `loans.view` / `loans.issue` / `loans.recover` / `loans.approve` / `loans.write-off` | Lifecycle |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-LOAN-FR-001 | Planned | Loan products: code, max tenure, interest_method (none/flat/reducing — config), rate, eligibility. |
| P12-LOAN-FR-002 | Planned | Loan accounts: principal, disbursed_at, schedule, balance, status. |
| P12-LOAN-FR-003 | Planned | Disbursement publishes `employee_loan.disbursed`. |
| P12-LOAN-FR-004 | Planned | Instalment schedule generated from product rules; editable before first recovery with audit. |
| P12-LOAN-FR-005 | Planned | Payroll recovery via deduction assignment; `employee_loan.recovered`. |
| P12-LOAN-FR-006 | Planned | Early settlement recalculates remaining interest per policy. |
| P12-LOAN-FR-007 | Planned | Write-off with approval publishes `employee_loan.written_off`. |
| P12-LOAN-FR-008 | Planned | Historical loans + schedules importable. |
| P12-LOAN-FR-009 | Planned | ESS view schedule and balance. |

---

## 4. Domain model

```text
employee_loan_products
- id, code, name, legal_entity_id, interest_method, annual_rate,
  max_tenure_months, max_principal, eligibility_json,
  account_mapping_key, effective_from / to, status

employee_loans
- id, loan_number, employee_id, product_id, principal, currency_code,
  disbursed_at, status, balance_principal, balance_interest,
  accounting_event_id nullable, timestamps

employee_loan_schedule_lines
- id, employee_loan_id, due_period_key, principal_due, interest_due,
  status (pending|paid|waived), payroll_run_id nullable

employee_loan_recoveries
- id, employee_loan_id, schedule_line_id nullable, amount, dates,
  accounting_event_id nullable
```

---

## 5. Services & interfaces

```text
EmployeeLoanService
LoanScheduleGenerator
```

---

## 6. Domain events

```text
employee_loan.disbursed
employee_loan.recovered
employee_loan.written_off
```

Mapping: `employee_loan_receivable`.

---

## 7. Configurability surface

* Products, interest methods, eligibility, write-off approval — config.

---

## 8. Historical migration inputs

* Open loans, remaining schedule, paid history.

---

## 9. Reports / ESS touchpoints

* Loan register / ageing; ESS schedule.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-LOAN-AC-001 | Planned | Disbursement does not write GL directly. |
| P12-LOAN-AC-002 | Planned | N instalments generated from tenure; sum principal = loan principal. |
| P12-LOAN-AC-003 | Planned | Payroll recovery marks schedule line paid and reduces balance. |
| P12-LOAN-AC-004 | Planned | Double recovery of same schedule line prevented. |

---

## 11. Out of scope / deferred hooks

* Mortgage / asset-backed loans with collateral module — use [asset-management.md](./asset-management.md) link optionally later.
