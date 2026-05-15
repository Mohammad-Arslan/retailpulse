# Phase 11 — Accounting & Financial Management

**SRS Reference:** §3.11  
**Status:** Planned  
**Depends on:** Phase 8, Phase 10

---

## Objective

**Double-entry accounting** with automatic posting rules, reconciliation, and financial statements.

## Database (key tables)

- `chart_of_accounts` — code, name, type (asset, liability, equity, revenue, expense), parent_id
- `journal_entries` — date, reference, description, posted_by
- `journal_transactions` — account_id, debit, credit
- `posting_rules` — event_type, debit_account_id, credit_account_id
- `bank_reconciliations`, `bank_statement_lines`

## Features

- Seed default chart of accounts
- Auto-post on: cash sale, card sale, purchase payment, payroll (Phase 12)
- Manual journal entry UI
- Bank reconciliation: import CSV, match to entries
- Reports: Trial Balance, P&L, Balance Sheet (date range)
- Permissions: `accounting.*` (Accountant + Owner)

## Acceptance Criteria

1. Completed cash sale auto-creates balanced journal entry.
2. Trial Balance debits equal credits for any date.
3. Accountant can reconcile imported bank line to journal entry.
