# Phase 12 — Expenses

**Gate / registry key:** `expenses`  
**Wave:** 3  
**Depends on:** — (accounting posting optional)  
**Status (module roll-up):** Implemented  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Operating expense entry (one-off and recurring), category hierarchy, approvals, attachments, and GL posting only via `expense.posted` / `expense.recurring_due` / `expense.reversed`.

---

## 2. Actors & permissions

```text
expenses.view / create / approve / post / reverse /
manage-categories / manage-recurring
```

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-EXP-FR-001 | Implemented | Expense categories: hierarchy, optional account_mapping_key, requires_receipt, default tax, status. |
| P12-EXP-FR-002 | Implemented | Expenses: number via DocumentNumberService, category, branch, entity, cost centre, vendor party, currency, amounts, tax, payment method, status lifecycle. |
| P12-EXP-FR-003 | Implemented | Attachments on configurable disk (not hardcoded local-only). |
| P12-EXP-FR-004 | Implemented | Receipt requirement per category, not global. |
| P12-EXP-FR-005 | Implemented | Approval via `expense_approval_policies` (amount/category/branch/entity, effective dating). |
| P12-EXP-FR-006 | Implemented | Approved/posted expense publishes `expense.posted`; no direct journals. |
| P12-EXP-FR-007 | Implemented | FX: transaction + functional amounts via CurrencyConversionService. |
| P12-EXP-FR-008 | Implemented | Recurring schedules with frequency, proration_policy, next_run_at. |
| P12-EXP-FR-009 | Implemented | Occurrences unique on `(schedule_id, period_key)`; scheduler never double-creates. |
| P12-EXP-FR-010 | Implemented | Recurring publishes `expense.recurring_due`; scheduler does not post journals itself. |
| P12-EXP-FR-011 | Implemented | Reversal via `expense.reversed`. |
| P12-EXP-FR-012 | Implemented | Module independently gateable without `hr`. |
| P12-EXP-FR-013 | Planned | Cost allocation split lines across cost centres (multi-line expenses). |
| P12-EXP-FR-014 | Planned | Historical expense import. |

---

## 4. Domain model

```text
expense_categories
- id, code, name, parent_id nullable, account_mapping_key nullable,
  is_group, requires_receipt, default_tax_type_id nullable, status

expenses
- id, expense_number, expense_category_id, branch_id, legal_entity_id,
  cost_centre_id nullable, vendor_party_type/id nullable,
  currency_code, exchange_rate nullable, amount, tax_type_id nullable,
  tax_amount, functional_amount, expense_date, payment_method nullable,
  description, status, approval_required, approved_by/at nullable,
  accounting_event_id nullable, journal_entry_id nullable,
  created_by / updated_by / timestamps

expense_attachments
- id, expense_id, disk, path, original_name, mime, size, uploaded_by, created_at

recurring_expense_schedules
- id, expense_category_id, branch_id, legal_entity_id, cost_centre_id nullable,
  currency_code, amount, tax_type_id nullable, frequency, interval_count,
  day_of_period nullable, start_date, end_date nullable, proration_policy,
  next_run_at, payment_method nullable, status, created_by, timestamps

recurring_expense_occurrences
- id, recurring_expense_schedule_id, period_key, scheduled_for,
  amount, functional_amount, status, expense_id nullable,
  accounting_event_id nullable, created_at
  UNIQUE (recurring_expense_schedule_id, period_key)

expense_approval_policies
- id, branch_id nullable, expense_category_id nullable, legal_entity_id nullable,
  min_amount, requires (pin|manager|workflow), approver_role nullable,
  effective_from / to, priority, status
```

---

## 5. Services & interfaces

```text
ExpenseService
RecurringExpenseScheduler
```

---

## 6. Domain events

```text
expense.posted
expense.recurring_due
expense.reversed
```

---

## 7. Configurability surface

* Categories, receipt rules, approvals, frequencies, mapping keys, disks — config.

---

## 8. Historical migration inputs

* Posted historical expenses as immutable imports (Planned).

---

## 9. Reports / ESS touchpoints

* Expense register by category; employee reimbursements are separate module.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-EXP-AC-001 | Implemented | Above-threshold expense blocked until approval then posts via event. |
| P12-EXP-AC-002 | Implemented | Recurring: exactly one occurrence per period_key; double scheduler safe. |
| P12-EXP-AC-003 | Implemented | FX expense stores transaction + functional and posts at resolved rate. |
| P12-EXP-AC-004 | Implemented | No ExpenseService JournalService write coupling. |
| P12-EXP-AC-005 | Implemented | Audit log on create/approve/post/reverse. |

---

## 11. Out of scope / deferred hooks

* Petty cash till management — Phase 11 petty cash events if used; not duplicated here.  
* Employee claim reimbursements — [reimbursements.md](./reimbursements.md).
