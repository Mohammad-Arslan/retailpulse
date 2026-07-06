# Phase 11 — Accounting & Financial Management

**SRS Reference:** §3.11, §3.18
**Status:** Planned
**Depends on:** Phase 8, Phase 9, Phase 10
**Related Future Phase:** Phase 12 — Payroll

---

## 1. Objective

Build a configurable, double-entry accounting module that automatically posts financial transactions from operational modules, supports manual journals, bank reconciliation, financial reporting, tax accounting, inventory valuation, multi-currency transactions, fiscal year closing, and audit-safe financial controls.

The accounting module must remain independent from Sales, Purchasing, Inventory, Payroll, and other operational modules. Operational modules will publish accounting events, while the accounting module will resolve configurable posting rules, account mappings, tax treatment, currency conversion, and journal entries.

No GL account, tax account, inventory account, revenue account, payment account, or workflow should be hardcoded in business logic.

---

## 2. Core Principles

* Every financial transaction follows double-entry accounting.
* Total debits must equal total credits for every posted journal entry.
* Posted journals are immutable.
* Corrections must be made through reversal entries only.
* System-generated journals must link to their originating document or event.
* Auto-posting must be idempotent to prevent duplicate journals.
* Posting rules, account mappings, tax rules, approval limits, numbering, and fiscal settings must be configurable per tenant.
* Branches, warehouses, product categories, currencies, tax types, and cost centres may use different GL mappings.
* Closed fiscal periods cannot be edited, posted into, reversed, or imported into without an approved reopening process.
* All accounting actions must be audit logged.

---

## 3. Accounting Event Architecture

Operational modules must publish events instead of directly creating journal entries.

Examples of accounting events:

```text
sale.completed
sale.returned
purchase.received
purchase.invoice_posted
purchase.returned
payment.received
payment.made
stock.adjusted
stock.scrapped
transfer.confirmed
payroll.posted
cheque.received
cheque.deposited
cheque.cleared
cheque.bounced
petty_cash.topped_up
petty_cash.disbursed
asset.acquired
asset.depreciation_due
asset.disposed
credit_note.issued
debit_note.issued
fx.revaluation_due
fiscal_year.closed
```

The Accounting module consumes these events through a configurable posting engine.

### Accounting Event Tracking

```text
accounting_events
- id
- event_type
- source_type
- source_id
- idempotency_key
- processing_status
- journal_entry_id nullable
- error_message nullable
- retry_count
- processed_at nullable
- created_at
- updated_at
```

A unique constraint must exist on:

```text
event_type + source_type + source_id
```

This ensures that a completed sale, payment, transfer, or other event cannot create duplicate journals if an API retries or a queue job is reprocessed.

---

## 4. Chart of Accounts

### 4.1 Chart of Accounts Table

```text
chart_of_accounts
- id
- code
- name
- type
  - asset
  - liability
  - equity
  - revenue
  - expense
- parent_id nullable
- account_level
- is_group
- is_postable
- branch_id nullable
- legal_entity_id nullable
- currency_id nullable
- status
- effective_from nullable
- effective_to nullable
- created_by
- updated_by
- created_at
- updated_at
```

The system must support account hierarchy, group accounts, posting accounts, branch-specific accounts, legal-entity-specific accounts, and inactive accounts.

### 4.2 Default Chart of Accounts Seeder

The default COA must be seeded as a configurable baseline and may be customized by the tenant.

Suggested default groups:

```text
Assets
- Cash on Hand
- Petty Cash
- Bank Accounts
- Accounts Receivable
- Inventory
- Cheques in Hand
- Cheques Deposited
- Fixed Assets
- Accumulated Depreciation

Liabilities
- Accounts Payable
- Tax Collected / Output VAT-GST
- Tax Payable
- Cheques Payable
- Intercompany Payable

Equity
- Owner Capital
- Retained Earnings
- Current Year Earnings
- Opening Balance Equity

Revenue
- Sales Revenue
- Other Income
- FX Gain

Expenses
- Cost of Goods Sold
- Payroll Expense
- Rent Expense
- Utilities Expense
- Depreciation Expense
- Scrapped Goods Expense
- Inventory Adjustment Expense
- Bank Charges
- Dishonour Charges
- FX Loss
```

All accounts must remain editable, configurable, and replaceable by tenant-level account mappings.

---

## 5. Account Mapping Configuration

The system must not rely on fixed account IDs in services or modules.

### 5.1 Account Mappings

```text
account_mappings
- id
- mapping_key
- account_id
- branch_id nullable
- warehouse_id nullable
- product_category_id nullable
- tax_type_id nullable
- currency_id nullable
- legal_entity_id nullable
- effective_from nullable
- effective_to nullable
- status
- priority
- created_at
- updated_at
```

Examples of mapping keys:

```text
sales_revenue
sales_discount
sales_return
cash_on_hand
bank_account
accounts_receivable
accounts_payable
inventory_asset
cogs
output_tax
input_tax
tax_payable
petty_cash
retained_earnings
opening_balance_equity
fx_gain
fx_loss
rounding_difference
suspense_account
inventory_adjustment
scrapped_goods
stock_gain
stock_loss
cheque_receivable
cheque_payable
cheques_in_hand
cheques_deposited
intercompany_receivable
intercompany_payable
depreciation_expense
accumulated_depreciation
```

The Account Resolver must select the most specific active mapping based on priority, legal entity, branch, warehouse, product category, tax type, currency, and effective date.

---

## 6. Posting Rule Engine

### 6.1 Posting Rule Sets

```text
posting_rule_sets
- id
- code
- name
- event_type
- entity_type nullable
- branch_id nullable
- legal_entity_id nullable
- currency_id nullable
- priority
- effective_from
- effective_to nullable
- status
- created_by
- updated_by
```

### 6.2 Posting Rule Lines

```text
posting_rule_lines
- id
- posting_rule_set_id
- sequence
- entry_side
  - debit
  - credit
- account_resolution_type
- account_id nullable
- account_mapping_key nullable
- amount_source
- tax_type_id nullable
- cost_centre_resolution_type nullable
- narration_template nullable
- required
- status
```

Supported account resolution types:

```text
fixed_account
account_mapping
customer_receivable_account
supplier_payable_account
payment_method_account
bank_account
warehouse_inventory_account
product_category_account
tax_account
cost_centre_account
employee_payable_account
intercompany_account
asset_account
configurable_mapping
```

Supported amount sources:

```text
gross_amount
net_amount
tax_amount
discount_amount
shipping_amount
inventory_cost
landed_cost
settlement_amount
exchange_difference
depreciation_amount
custom_formula
```

Posting rules must be versioned by effective date and configurable through the Accounting Settings UI.

---

## 7. Journal Management

### 7.1 Journal Entries

```text
journal_entries
- id
- journal_number
- journal_date
- fiscal_year_id
- legal_entity_id nullable
- branch_id nullable
- reference
- description
- source_module nullable
- source_event nullable
- source_reference_type nullable
- source_reference_id nullable
- source_number nullable
- status
  - draft
  - pending_approval
  - approved
  - posted
  - reversed
- is_system_generated
- is_opening_balance
- is_closing_entry
- reversal_of_journal_entry_id nullable
- posted_at nullable
- posted_by nullable
- approved_by nullable
- locked_at nullable
- created_by
- updated_by
- created_at
- updated_at
```

### 7.2 Journal Transactions

```text
journal_transactions
- id
- journal_entry_id
- line_sequence
- account_id
- debit
- credit
- functional_currency_amount
- transaction_currency_amount nullable
- currency_id
- exchange_rate nullable
- cost_centre_id nullable
- branch_id nullable
- warehouse_id nullable
- party_type nullable
- party_id nullable
- product_variant_id nullable
- tax_type_id nullable
- reference_type nullable
- reference_id nullable
- description nullable
- created_at
- updated_at
```

### 7.3 Journal Rules

* A journal cannot be posted unless total debit equals total credit.
* A posted journal cannot be edited or deleted.
* Corrections must create a reversal journal linked to the original journal.
* Reversal entries must reverse all original lines.
* System-generated journals must include source references.
* Manual journals may require approval based on configurable amount limits.
* All journal changes must be audit logged.
* Journals cannot be posted into closed fiscal periods.
* Journals cannot be posted before the accounting cutover date unless specifically approved.

---

## 8. Manual Journal Entry

The system must provide a Manual Journal Entry UI for authorized finance users.

Features:

* Select journal date, branch, legal entity, currency, cost centre, and description.
* Add multiple debit and credit lines.
* Select account, party, tax type, warehouse, and cost centre per line.
* Attach supporting documents.
* Save as draft.
* Submit for approval.
* Approve and post based on configured permissions.
* Reverse posted journals.
* View audit history.
* Prevent posting unless debits equal credits.

---

## 9. Opening Balance Import

### 9.1 Import Features

The system must support bulk import for:

* Chart of Accounts
* Opening journal balances
* Customer opening balances
* Supplier opening balances
* Inventory opening valuation
* Bank opening balances
* Tax opening balances

### 9.2 Import Tables

```text
coa_import_batches
- id
- file_name
- imported_by
- status
- validation_summary
- approved_by nullable
- created_at

opening_balance_import_batches
- id
- cutover_date
- file_name
- imported_by
- status
- validation_summary
- approved_by nullable
- posted_journal_entry_id nullable
- created_at

opening_balance_import_lines
- id
- opening_balance_import_batch_id
- account_id
- debit
- credit
- party_type nullable
- party_id nullable
- warehouse_id nullable
- product_variant_id nullable
- cost_centre_id nullable
- validation_status
- validation_message nullable
```

### 9.3 Opening Balance Rules

* Opening balance imports must create one balanced opening journal entry.
* Opening balances must be marked as `is_opening_balance = true`.
* Opening balance journals must not trigger live auto-posting rules.
* AR opening balances must reconcile with customer-level opening balances.
* AP opening balances must reconcile with supplier-level opening balances.
* Inventory opening balances must reconcile with opening stock valuation.
* Bank opening balances must reconcile with bank statement opening balances.
* Opening balances must use the configured Opening Balance Equity account where required.
* A cutover lock date must prevent operational transactions from posting before go-live.

Permissions:

```text
accounting.import-coa
accounting.import-opening-balances
```

---

## 10. Cost Centres

### 10.1 Cost Centre Table

```text
cost_centres
- id
- name
- code
- parent_id nullable
- branch_id nullable
- legal_entity_id nullable
- status
- created_at
- updated_at
```

A cost centre may be branch-specific, legal-entity-specific, or cross-branch.

### 10.2 Cost Allocation

```text
cost_centre_allocations
- id
- source_journal_transaction_id
- cost_centre_id
- allocation_method
- allocation_percent nullable
- allocated_amount
- created_at
```

Supported allocation methods:

```text
percentage
headcount
revenue_share
floor_area
equal_split
manual
```

### 10.3 Cost Centre Features

* Cost Centre CRUD under Accounting Settings.
* Optional cost centre assignment on journal lines.
* Cost Centre P&L report.
* Cost Centre hierarchy support.
* Shared expense allocation support.
* Branch and cost-centre filtering in reports.

---

## 11. Tax Ledger and Tax Configuration

### 11.1 Tax Types

```text
tax_types
- id
- name
- code
- rate
- tax_direction
  - sales
  - purchase
  - both
- calculation_method
  - inclusive
  - exclusive
- output_tax_account_id nullable
- input_tax_account_id nullable
- tax_payable_account_id nullable
- recoverable_percentage
- effective_from
- effective_to nullable
- status
```

### 11.2 Tax Rules

* Tax accounts must be configurable per tax type.
* Tax may be inclusive or exclusive.
* Multiple tax rates must be supported.
* Exempt, zero-rated, recoverable, non-recoverable, withholding, VAT, GST, and sales tax scenarios must be configurable.
* Sales with tax must post separately to Revenue and Output Tax.
* Purchases with recoverable tax must post separately to Expense/Inventory and Input Tax.
* Tax reporting must be filterable by tax type, branch, legal entity, and date range.

### 11.3 Tax Return Summary Report

The report must include:

* Total tax collected.
* Total tax paid or recoverable.
* Net tax payable.
* Tax by type.
* Tax by branch.
* Tax by legal entity.
* Tax by invoice, credit note, debit note, and adjustment.

---

## 12. Inventory Costing and COGS Posting

### 12.1 Inventory Cost Layers

```text
inventory_cost_layers
- id
- product_variant_id
- warehouse_id
- batch_no nullable
- received_at
- qty_received
- qty_remaining
- unit_cost
- valuation_method
  - fifo
  - wac
- landed_cost_amount
- source_reference_type
- source_reference_id
- status
```

### 12.2 Cost Service

The Cost Service must resolve inventory cost at the time of sale completion.

Supported valuation methods:

```text
FIFO
WAC
LIFO report-only
```

Valuation may be configured globally, per product category, per warehouse, or per tenant.

### 12.3 Sale Posting

A completed sale must create both revenue and inventory-cost entries.

Example configurable posting:

```text
Debit Cash / Bank / Accounts Receivable
Credit Sales Revenue
Credit Output Tax

Debit Cost of Goods Sold
Credit Inventory Asset
```

### 12.4 Inventory Accounting Rules

* Landed cost from Phase 10 must be included in inventory layer unit cost.
* Sales returns must restore inventory at original cost where available.
* Scrap must debit Scrapped Goods Expense and credit Inventory.
* Stock gains and losses must post to configurable adjustment accounts.
* Negative stock policy must be configurable.
* Zero-cost stock policy must be configurable.
* Backdated receipt and landed-cost adjustment handling must be configurable.
* Inventory account mappings may vary by warehouse, branch, product category, and legal entity.

---

## 13. Inter-Branch and Intercompany Accounting

### 13.1 Legal Entity Structure

```text
organization_entities
- id
- tenant_id
- legal_name
- tax_registration_no nullable
- functional_currency_id
- status
```

```text
branch_accounting_profiles
- id
- branch_id
- legal_entity_id
- interbranch_accounting_enabled
- due_from_account_id nullable
- due_to_account_id nullable
- status
```

### 13.2 Intercompany Transactions

```text
intercompany_transactions
- id
- transfer_reference_type
- transfer_reference_id
- source_legal_entity_id
- destination_legal_entity_id
- source_journal_entry_id
- destination_journal_entry_id
- settlement_status
- settled_at nullable
- created_at
```

### 13.3 Transfer Rules

The system must support two configurable modes:

```text
Same Legal Entity Transfer
- Inventory movement
- Warehouse and branch reporting
- Optional branch-level clearing accounts

Separate Legal Entity Transfer
- Source: Debit Intercompany Receivable, Credit Inventory
- Destination: Debit Inventory, Credit Intercompany Payable
- Cost based on FIFO or WAC
- Periodic settlement support
```

All intercompany and interbranch accounts must be resolved through account mappings.

---

## 14. Multi-Currency Support

### 14.1 Currencies

```text
currencies
- id
- code
- name
- symbol
- decimal_places
- status
```

### 14.2 Exchange Rates

```text
exchange_rates
- id
- currency_id
- rate_date
- rate_type
  - spot
  - average
  - closing
  - custom
- rate
- source nullable
- approved_by nullable
- status
- created_at
```

### 14.3 Currency Rules

* Each legal entity must have one functional currency.
* Sales, purchase orders, invoices, payments, credit notes, debit notes, and journals may have transaction currency.
* Journal lines must store both original transaction currency amount and functional currency amount.
* Exchange rate overrides must follow configurable approval rules.
* FX gain and loss accounts must be configurable.
* Period-end FX revaluation must create unrealized gain/loss journals.
* Settlement must create realized FX gain/loss journals.
* Currency rounding rules must be configurable.

### 14.4 Reports

* Multi-currency Trial Balance.
* Foreign currency account balances.
* FX revaluation report.
* Realized and unrealized FX gain/loss report.

---

## 15. Credit Notes and Debit Notes

### 15.1 Credit Notes

```text
credit_notes
- id
- credit_note_number
- customer_id
- invoice_id nullable
- date
- currency_id
- exchange_rate nullable
- amount
- tax_amount
- reason
- status
- journal_entry_id nullable
- created_by
```

Credit notes may be issued for:

* Customer returns.
* Overcharges.
* Pricing corrections.
* Goodwill adjustments.
* Tax corrections.

Credit notes must reduce Accounts Receivable and appear on the customer statement.

### 15.2 Debit Notes

Supplier debit notes from Phase 10 RMA must integrate with Accounts Payable.

```text
debit_notes
- id
- debit_note_number
- supplier_id
- purchase_invoice_id nullable
- date
- currency_id
- exchange_rate nullable
- amount
- tax_amount
- reason
- status
- journal_entry_id nullable
- created_by
```

Credit notes and debit notes must be printable, emailable, numbered independently, audit logged, and linked to originating documents where applicable.

---

## 16. Bank Accounts and Bank Reconciliation

### 16.1 Bank Accounts

```text
bank_accounts
- id
- branch_id nullable
- legal_entity_id nullable
- coa_account_id
- bank_name
- account_title
- account_number_masked
- currency_id
- status
```

### 16.2 Bank Statement Lines

```text
bank_statement_lines
- id
- bank_account_id
- statement_date
- transaction_date
- reference
- description
- debit
- credit
- running_balance nullable
- import_batch_id
- status
  - unmatched
  - suggested
  - matched
  - ignored
  - reconciled
```

### 16.3 Reconciliation Matches

```text
bank_reconciliation_matches
- id
- bank_statement_line_id
- journal_transaction_id
- matched_amount
- match_type
- matched_by
- matched_at
```

### 16.4 Reconciliation Features

* Import bank statements through configurable CSV templates.
* Detect duplicate statement imports.
* Support one-to-one matching.
* Support one-to-many matching.
* Support many-to-one matching.
* Support partial matching.
* Suggest matches using amount, date, reference, cheque number, and narration.
* Require approval for reconciliation adjustments.
* Show unreconciled bank aging.
* Reconcile opening and closing statement balances.
* Support bank account-specific GL mapping.

---

## 17. Petty Cash Management

### 17.1 Petty Cash Registers

```text
petty_cash_registers
- id
- branch_id
- legal_entity_id
- name
- coa_account_id
- cashier_user_id nullable
- opening_balance
- current_balance
- register_mode
  - imprest
  - running_balance
- variance_tolerance_amount
- status
```

### 17.2 Petty Cash Vouchers

```text
petty_cash_vouchers
- id
- voucher_number
- petty_cash_register_id
- voucher_type
  - top_up
  - disbursement
  - adjustment
- date
- amount
- expense_account_id nullable
- cost_centre_id nullable
- attachment_path nullable
- approval_status
- journal_entry_id nullable
- created_by
```

### 17.3 Petty Cash Rules

* Top-up vouchers must debit Petty Cash and credit Cash or Bank.
* Disbursement vouchers must debit Expense and credit Petty Cash.
* Receipt attachment requirement must be configurable.
* Approval thresholds must be configurable by branch and amount.
* Cashier must declare actual cash during reconciliation.
* Variances above tolerance must require manager approval.
* Expense category restrictions must be configurable.

---

## 18. Cheque Management

### 18.1 Cheques

```text
cheques
- id
- type
  - issued
  - received
- party_id
- party_type
- amount
- currency_id
- exchange_rate nullable
- cheque_no
- bank
- due_date
- status
  - pending
  - deposited
  - cleared
  - bounced
  - cancelled
- related_journal_entry_id nullable
- created_at
- updated_at
```

### 18.2 Cheque Accounting Flow

Received cheque:

```text
Debit Cheques in Hand
Credit Accounts Receivable
```

Cheque deposited:

```text
Debit Cheques Deposited
Credit Cheques in Hand
```

Cheque cleared:

```text
Debit Bank
Credit Cheques Deposited
```

Cheque bounced:

```text
Debit Accounts Receivable
Credit Cheques Deposited or Bank

Debit Dishonour Charges Receivable or Expense
Credit Configured Dishonour Charges Account
```

All cheque accounts and charges must be resolved through configurable account mappings.

---

## 19. Fixed Assets and Depreciation

### 19.1 Fixed Assets

```text
fixed_assets
- id
- asset_code
- name
- category_id
- acquisition_cost
- acquisition_date
- useful_life_months
- salvage_value
- depreciation_method
  - straight_line
- depreciation_start_convention
- asset_account_id
- accumulated_depreciation_account_id
- depreciation_expense_account_id
- branch_id nullable
- legal_entity_id
- location nullable
- custodian_user_id nullable
- status
```

### 19.2 Asset Features

* Asset categories.
* Capitalization threshold configuration.
* Asset acquisition.
* Monthly depreciation posting.
* Asset transfer between branches.
* Asset disposal.
* Asset write-off.
* Asset impairment.
* Depreciation pause after disposal.
* Partial-month depreciation rules.
* Asset register report.
* Net book value report as of any date.

Monthly depreciation posting:

```text
Debit Depreciation Expense
Credit Accumulated Depreciation
```

All asset-related accounts must be configurable.

---

## 20. Fiscal Years and Period Closing

### 20.1 Fiscal Years

```text
fiscal_years
- id
- name
- legal_entity_id
- start_date
- end_date
- status
  - open
  - closing
  - closed
- closed_at nullable
- closed_by nullable
- reopened_at nullable
- reopened_by nullable
```

### 20.2 Fiscal Year Settings

```text
financial_settings
- id
- tenant_id
- functional_currency_id
- fiscal_year_start_month
- retained_earnings_account_id
- current_year_earnings_account_id nullable
- opening_balance_equity_account_id
- suspense_account_id
- rounding_account_id
- fx_gain_account_id
- fx_loss_account_id
- default_inventory_valuation_method
- allow_negative_inventory
- allow_manual_journal_posting
- manual_journal_approval_limit
- backdated_posting_policy
- backdated_entry_approval_required
- fiscal_year_close_approval_required
- period_lock_mode
- default_tax_type_id nullable
- journal_numbering_mode
```

### 20.3 Fiscal Close Procedure

The fiscal year close must run as a supervised job.

The procedure must:

1. Validate that all journals in the period are balanced and posted.
2. Lock all journal entries for the fiscal period.
3. Calculate net income for the fiscal year.
4. Post a closing journal moving net income to the configured Retained Earnings account.
5. Mark the fiscal year as closed.
6. Generate a fiscal close audit report.

Closed periods must reject:

* New journal entries.
* Journal edits.
* Reversals.
* Opening balance imports.
* Backdated operational postings.
* Manual adjustments.

Reopening a fiscal year must require configurable dual approval and create a complete audit trail.

---

## 21. Document Numbering

### 21.1 Document Sequences

```text
document_sequences
- id
- document_type
- branch_id nullable
- legal_entity_id nullable
- prefix
- next_number
- reset_frequency
- fiscal_year_id nullable
- status
```

Supported document types:

```text
journal_voucher
payment_voucher
receipt_voucher
credit_note
debit_note
petty_cash_voucher
bank_reconciliation_batch
fiscal_closing_entry
asset_disposal_voucher
cheque_register_entry
```

Document numbering must be configurable by tenant, legal entity, branch, fiscal year, and document type.

---

## 22. Financial Reports

### 22.1 Required Reports

* Trial Balance.
* Profit and Loss Statement.
* Balance Sheet.
* General Ledger.
* Account Ledger.
* Cash Flow Statement.
* Cost Centre P&L.
* Tax Return Summary.
* Tax Ledger.
* Bank Book.
* Bank Reconciliation Report.
* AR Aging.
* AP Aging.
* Journal Register.
* Unposted Journal Report.
* Audit Trail Report.
* Inventory Valuation Report.
* Inventory Movement Report.
* Asset Register Report.
* Depreciation Report.
* FX Revaluation Report.
* Intercompany Balance Report.
* Petty Cash Report.
* Cheque Status Report.

### 22.2 Reporting Filters

Reports must support filtering by:

* Date range.
* Fiscal year.
* Legal entity.
* Branch.
* Warehouse.
* Cost centre.
* Currency.
* Tax type.
* Account range.
* Account hierarchy.
* Journal status.
* Party.
* Product category where applicable.

Reports must support:

* Opening balance.
* Period movement.
* Closing balance.
* Comparative periods.
* Drill-down from account balance to journal lines.
* Drill-down from journal line to source document.
* Export to CSV, PDF, and XLSX.

---

## 23. Permissions

```text
accounting.view
accounting.manage-coa
accounting.manage-mappings
accounting.manage-posting-rules
accounting.create-journal
accounting.approve-journal
accounting.post-journal
accounting.reverse-journal
accounting.import-coa
accounting.import-opening-balances
accounting.manage-cost-centres
accounting.manage-fiscal-years
accounting.close-fiscal-year
accounting.reopen-fiscal-year
accounting.manage-bank-accounts
accounting.import-bank-statements
accounting.reconcile-bank
accounting.manage-petty-cash
accounting.manage-cheques
accounting.manage-assets
accounting.manage-tax-settings
accounting.view-reports
accounting.export-reports
```

Default roles may include Owner, Accountant, Finance Manager, Cashier, and Auditor, but all permissions must remain configurable per tenant.

---

## 24. Services and Module Boundaries

The following services must remain independent and reusable:

```text
AccountingEventService
PostingRuleEngine
AccountResolverService
JournalService
JournalValidationService
TaxCalculationService
CurrencyConversionService
CostService
BankReconciliationService
FiscalCloseService
FinancialReportingService
PettyCashService
ChequeService
AssetDepreciationService
```

Operational modules must not directly create debit and credit lines. They must publish events with sufficient contextual data for the Accounting module to resolve the correct posting rules.

---

## 25. Acceptance Criteria

1. A completed cash sale creates one balanced journal entry through configurable posting rules.
2. A completed card sale can post to a different payment account through account mappings.
3. A taxed sale posts revenue, tax, COGS, and inventory entries separately in the same accounting transaction.
4. A completed sale posts COGS and Inventory credit at resolved FIFO or WAC cost.
5. Reprocessing the same source event does not create duplicate journal entries.
6. Trial Balance total debits equal total credits for any date range.
7. Every posted journal links to its source document or source event.
8. Posting rules can be changed through configuration without code deployment.
9. Posting rules support effective dates and version history.
10. A branch can use different cash, bank, inventory, revenue, expense, and tax account mappings.
11. A posted journal cannot be edited or deleted.
12. A correction creates a reversal journal linked to the original journal.
13. An attempt to edit a posted journal entry is rejected.
14. Opening balance import creates one balanced opening journal entry.
15. Opening balances are excluded from live auto-posting rules.
16. Opening balances reconcile with customer, supplier, inventory, bank, and tax sub-ledgers.
17. Accountant can import bank statement CSV files using configured bank templates.
18. Accountant can reconcile one bank line with one or multiple journal transactions.
19. Bank reconciliation supports partial and grouped matching.
20. Cost Centre P&L can be filtered by centre and date range.
21. Fiscal close locks the period, calculates net income, posts a retained earnings entry, and marks the fiscal year as closed.
22. Closed periods reject new postings, edits, reversals, and imports.
23. Fiscal year reopening requires configurable dual approval and is audit logged.
24. Foreign currency supplier invoices store both original and functional currency amounts.
25. FX settlement and revaluation post to configurable FX gain/loss accounts.
26. Inter-branch transfer confirmation creates matching entries at the same FIFO or WAC cost.
27. Separate legal entity transfers create configurable Intercompany Receivable and Intercompany Payable entries.
28. Credit notes reduce customer AR and appear on customer statements.
29. Supplier debit notes reduce AP and link to purchase documents where applicable.
30. Petty cash reconciliation records actual cash and routes material variances for approval.
31. Cheque status changes create the appropriate configurable journal entries.
32. Monthly depreciation posts debit Depreciation Expense and credit Accumulated Depreciation.
33. Financial reports support drill-down from report balances to journal transactions and source documents.
34. Failed accounting events are logged, retryable, and visible to authorized finance users.
35. All accounting actions, approvals, reversals, imports, reconciliations, and fiscal close actions are audit logged.
