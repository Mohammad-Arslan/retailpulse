# Phase 12 — Expense Reimbursements (Employee Claims)

**Gate / registry key:** `reimbursements`  
**Wave:** 3  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

Distinct from operating [expenses.md](./expenses.md) (vendor/company spend): this module is employee-submitted claims.

---

## 1. Objective

Employee expense claims with category policies, receipts, approvals, and settlement via payroll or AP — GL via `reimbursement.posted` / `reimbursement.reversed`.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `reimbursements.view` / `create` / `approve` / `post` | Lifecycle |
| `selfservice.view-own` | Submit own claims |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-REIMB-FR-001 | Planned | Claim types/categories with per-category receipt rules and max amounts. |
| P12-REIMB-FR-002 | Planned | Claims: employee, lines (date, amount, category, tax), attachments, status. |
| P12-REIMB-FR-003 | Planned | Approval policies (manager / amount / workflow hook). |
| P12-REIMB-FR-004 | Planned | Settlement mode config: payroll_component | accounts_payable | payment_method. |
| P12-REIMB-FR-005 | Planned | Post publishes `reimbursement.posted`; mapping `reimbursement_payable`. |
| P12-REIMB-FR-006 | Planned | Payroll settlement creates reimbursement component on next run. |
| P12-REIMB-FR-007 | Planned | Historical claims import. |
| P12-REIMB-FR-008 | Planned | ESS submit/track claims. |

---

## 4. Domain model

```text
reimbursement_categories
- id, code, name, requires_receipt, max_amount nullable,
  account_mapping_key nullable, status

reimbursement_claims
- id, claim_number, employee_id, legal_entity_id, branch_id,
  status, total_amount, currency_code, settlement_mode,
  accounting_event_id nullable, payroll_run_id nullable, timestamps

reimbursement_claim_lines
- id, claim_id, expense_date, category_id, description,
  amount, tax_amount, receipt_attachment_id nullable

reimbursement_approval_policies
- similar pattern to expense_approval_policies
```

---

## 5. Services & interfaces

```text
ReimbursementService
ReimbursementPayrollBridge
```

---

## 6. Domain events

```text
reimbursement.posted
reimbursement.reversed
```

---

## 7. Configurability surface

* Categories, limits, settlement mode, approvals — config.

---

## 8. Historical migration inputs

* Open/paid claims.

---

## 9. Reports / ESS touchpoints

* Claims ageing; ESS primary UI.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-REIMB-AC-001 | Planned | Category requiring receipt blocks submit without attachment. |
| P12-REIMB-AC-002 | Planned | Payroll settlement mode includes amount on payslip component. |
| P12-REIMB-AC-003 | Planned | No direct journal writes from ReimbursementService. |

---

## 11. Out of scope / deferred hooks

* Corporate card feeds — future import provider.
