# Phase 11 — Accounting & Financial Management

**SRS Reference:** §3.11, §3.18 (opening balances & COA)  
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
- **Bulk import (§3.18):** chart of accounts from template; opening journal balances (debit/credit per account as of cutover date); `accounting.import-coa`, `accounting.import-opening-balances`

## Acceptance Criteria

1. Completed cash sale auto-creates balanced journal entry.
2. Trial Balance debits equal credits for any date.
3. Accountant can reconcile imported bank line to journal entry.
4. Opening balance import produces balanced journal entry; excluded from live auto-posting rules.

---

## Phase Enhancements (SRS v4.0 — baseline)

### Cost Centres (§3.11)
- `cost_centres` table: `id`, `name`, `code`, `parent_id` (nullable, for hierarchy), `branch_id` (nullable for cross-branch centres), `status`.
- Optional `cost_centre_id` column on `journal_transactions`; NULL means unallocated.
- Cost centre P&L report: filterable by centre and date range.
- Admin UI: Cost Centre CRUD under Accounting settings.

### Fiscal Year Close
- `fiscal_years` table: `id`, `name`, `start_date`, `end_date`, `status` (`open`/`closing`/`closed`).
- Close procedure: supervised job that (1) locks all journal entries for the period, (2) calculates net income, (3) posts a closing entry moving net income to Retained Earnings, (4) marks fiscal year `closed`.
- Closed periods are non-editable; Super Admin can re-open with dual-approval.

### Tax Ledger GL Accounts
- Seeder creates dedicated COA accounts: `Tax Collected (Output VAT/GST)` and `Tax Payable` under the Liabilities group.
- Automatic posting rules updated: sale with tax posts debit `Cash/AR`, credit `Sales Revenue` + credit `Tax Collected`.
- Tax return summary report: total tax collected per period, filterable by tax type.

### Petty Cash Module
- `petty_cash_registers` table: `id`, `branch_id`, `name`, `opening_balance`, `current_balance`.
- `petty_cash_transactions`: top-up vouchers (debit cash register, credit petty cash) and disbursements (debit expense, credit petty cash).
- Reconciliation: cashier declares actual cash; variance triggers manager approval.

### Cheque Management
- `cheques` table: `id`, `type` (`issued`/`received`), `party_id`, `party_type`, `amount`, `cheque_no`, `bank`, `due_date`, `status` (`pending`/`deposited`/`cleared`/`bounced`/`cancelled`).
- Status updates trigger corresponding journal entries (e.g., bounced cheque reverses the original receipt entry and charges a dishonour fee).

### Asset Register & Depreciation (stub)
- `fixed_assets`: `id`, `name`, `category`, `acquisition_cost`, `acquisition_date`, `useful_life_months`, `salvage_value`, `depreciation_method` (`straight_line`), `coa_account_id`.
- Monthly scheduled job posts depreciation journal: debit `Depreciation Expense`, credit `Accumulated Depreciation`.
- Asset register report: net book value per asset as of any date.

---

## SRS v4.0 Enhancements (§3.11)

### COGS Posting Specification

- **`inventory_cost_layers`** — FIFO stack: `product_variant_id`, `warehouse_id`, `batch_no`, `received_at`, `qty_remaining`, `unit_cost`, `valuation_method`.
- **`CostService`** — resolves cost at sale completion (real-time, not batch).
- Valuation: FIFO or WAC per category/global (§3.25); LIFO report-only.
- Sale GL: Debit COGS, Credit Inventory (separate from revenue entry).
- Return restock restores layer at original cost; scrap posts to Scrapped Goods Expense.
- Landed cost from Phase 10 included in layer `unit_cost`.

### Inter-Company & Inter-Branch Accounting

- On transfer confirm: source Debit Intercompany Receivable / Credit Inventory; destination Debit Inventory / Credit Intercompany Payable — at FIFO/WAC cost.
- **`intercompany_transactions`** — links transfer to journal entries; periodic settlement job nets balances.

### Multi-Currency Support

- **`currencies` / `exchange_rates`** — functional currency per tenant; transaction currency on sales/POs/invoices.
- Store original + functional amounts; period-end FX revaluation; realized FX on settlement.
- Multi-currency trial balance report.

### Credit Note & Debit Note Documents

- **`credit_notes`** — customer overcharges/returns/goodwill; numbered separately from invoices; reduces AR.
- Supplier **`debit_notes`** (from Phase 10 RMA) integrated into AP ledger.
- Printable/emailable; reference originating invoice; audit-logged.

### Journal Immutability

- Posted journals cannot be edited; corrections via reversal entries only (§4.3).

### Acceptance Criteria (v4.0)

1. Completed sale posts COGS + Inventory credit at resolved FIFO cost in same transaction as revenue.
2. Inter-branch transfer confirm creates matching intercompany entries at same cost value.
3. Foreign-currency supplier invoice stores both original and functional amounts.
4. Credit note reduces customer AR and appears on customer statement (Phase 9).
5. Attempt to edit posted journal entry is rejected; reversal entry balances books.
