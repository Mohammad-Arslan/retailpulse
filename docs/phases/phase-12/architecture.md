# Phase 12 — Architecture Principles

**Binding on:** every module under `docs/phases/phase-12/`  
**SRS Reference:** §3.12, §3.13  
**Depends on:** Phase 1 (RBAC, audit), Phase 3 (branches / legal entities), Phase 11 (accounting events, posting rules, document numbering, currency), Phase 7 (optional attendance source: POS PIN)  
**Related future phases:** Phase 23 (Module Config Engine), Phase 26 (Employee mobile), Phase 29 (Workflow Engine)

Modules MUST NOT restate these principles. Each module SRS says: *This module follows the Phase 12 Architecture Principles.*

---

## 1. Objective

Deliver a commercial, enterprise-grade, modular HRMS and operating-expense suite that is fully configurable per tenant, loosely coupled from Accounting and other retail modules, and implementable module-by-module behind sellable gates.

---

## 2. Non-functional design principles (NFR)

| ID | Principle | Rule |
| :--- | :--- | :--- |
| NFR-CFG | Nothing hardcoded | Salary components, tax methods, provident-fund rules, leave rules, approval limits, overtime policies, deduction policies, numbering schemes, statutory contributions, holidays, reporting hierarchies, and workflows are configuration-driven. Editable without a code deployment. |
| NFR-MIG | Historical migration first-class | Every module supports importing opening balances, historical transactions, YTD values, and immutable historical records with validation, reconciliation, retryable sessions, and audit trails. See [historical-migration.md](./historical-migration.md). |
| NFR-ED | Effective-dated configuration | Policies, rates, structures, assignments, and statutory rules support `effective_from` / `effective_to` so historical calculations remain reproducible. Posted snapshots never change when config changes. |
| NFR-CN | Country-neutral architecture | Jurisdiction-specific logic (e.g. Pakistan FBR YTD cumulative tax) is a configurable **provider** or **method**. Other countries use other providers without changing payroll orchestration code. |
| NFR-FY | Fiscal-year awareness | Leave accruals, entitlements, carry-forward, encashment, and YTD payroll/tax calculations operate against configurable fiscal years (not assumed calendar years). |
| NFR-EVT | Event-driven & loosely coupled | HRMS modules publish domain events. Accounting, Notifications, Reporting, and future modules consume them. No direct journal or GL account writes from Phase 12 services. |
| NFR-SCALE | Enterprise scalability | Multi-tenant, multi-company (legal entity), multi-branch, multi-currency, RBAC, audit logging, configurable approvals, and scheduled background processing. |
| NFR-IDEM | Idempotent posting | Accounting events carry deterministic idempotency keys. Reprocessing never double-posts. |
| NFR-AUDIT | Audit-logged | Create / approve / reject / post / reverse / import / adjust actions are audit logged (Phase 1 audit trail). |
| NFR-GATE | Module-gated | Capabilities are independently enableable/sellable per branch/tenant via `config/hr_payroll_modules.php` (swap to Phase 23 registry later). |

---

## 3. Multi-tenant / multi-entity / multi-branch / multi-currency

* Employees, payroll runs, expenses, loans, PF accounts, and related masters belong to a **legal entity** with a functional currency.
* Branch scope applies to attendance, expenses, roster, approvals, and module gates.
* Foreign-currency amounts store transaction + functional amounts; conversion uses Phase 11 `CurrencyConversionService`.
* Nothing assumes a single currency, entity, or branch.

---

## 4. Module gating

Gated per branch via branch HR profile + `EnsureModuleEnabled` middleware. Nav visibility via `enabledHrModules` Inertia prop.

Canonical dependency graph lives in [module-registry.md](./module-registry.md). Baseline example:

```text
hr                      => requires []
attendance              => requires [hr]
leave                   => requires [hr]
overtime                => requires [hr, attendance]
shifts_roster           => requires [hr]
holiday_calendar        => requires [hr]
payroll                 => requires [hr]
tax_engine              => requires [payroll]
statutory               => requires [payroll]
provident_fund          => requires [payroll]
salary_advance          => requires [payroll]
employee_loans          => requires [payroll]
payroll_adjustments     => requires [payroll]
expenses                => requires []
reimbursements          => requires [hr]
appraisal               => requires [hr]
recruitment             => requires [hr]
onboarding              => requires [hr]
employee_assets         => requires [hr]
employee_self_service   => requires [hr]
hrms_reports            => requires [hr]
```

---

## 5. Accounting decoupling contract

Phase 12 services MUST NOT:

* Import `JournalService`, `PostingRuleEngine`, or GL account models for writing.
* Construct `journal_entries` / `journal_transactions`.
* Store hardcoded GL account IDs.

They publish accounting events; Phase 11 resolves posting rules and account mappings.

### 5.1 Event catalogue

```text
# Expenses & reimbursements
expense.posted
expense.recurring_due
expense.reversed
reimbursement.posted
reimbursement.reversed

# Payroll
payroll.posted
payroll.approved            # optional: post on approval instead of generation (config)
payroll.reversed

# Advances & loans
employee_advance.issued
employee_advance.recovered
employee_loan.disbursed
employee_loan.recovered
employee_loan.written_off

# Provident fund
provident_fund.contribution_posted
provident_fund.withdrawal_posted
provident_fund.settlement_posted
provident_fund.interest_posted

# Assets (employee-issued)
employee_asset.issued
employee_asset.returned
employee_asset.written_off
```

### 5.2 Event payload (context, not accounts)

Amounts, `branch_id`, `legal_entity_id`, `currency_code`, category / component / scheme identifiers, `party` (employee/vendor), `cost_centre_id`, `tax_type_id`, `date`, and module-specific keys. Posting-rule sets resolve debit/credit accounts via mapping keys.

### 5.3 Idempotency keys

```text
expense.posted:Expense:{id}
expense.recurring_due:RecurringExpenseOccurrence:{id}
reimbursement.posted:Reimbursement:{id}
payroll.posted:PayrollRun:{id}
employee_advance.issued:EmployeeAdvance:{id}
employee_advance.recovered:EmployeeAdvanceRecovery:{id}
employee_loan.disbursed:EmployeeLoan:{id}
employee_loan.recovered:EmployeeLoanRecovery:{id}
provident_fund.contribution_posted:PfContribution:{id}
provident_fund.withdrawal_posted:PfWithdrawal:{id}
provident_fund.settlement_posted:PfSettlement:{id}
provident_fund.interest_posted:PfInterestRun:{id}
employee_asset.issued:EmployeeAssetIssue:{id}
```

### 5.4 Account mapping keys (baseline)

Seeded editable via Phase 11 Account Mappings UI. Modules may document additional keys; register them in the seeding list and [module-registry.md](./module-registry.md) notes.

```text
expense_default
expense_<category_code>
payroll_expense
overtime_expense
employer_contribution_expense
net_salary_payable
tax_withheld_payable
statutory_payable_<scheme_code>
employee_advance_receivable
employee_loan_receivable
reimbursement_payable
provident_fund_payable
provident_fund_expense
provident_fund_asset
employee_asset_clearing
```

Posting-rule sets seeded (baseline): `expense.posted`, `expense.recurring_due`, `payroll.posted`, `employee_advance.issued`. Additional sets are added when their module is implemented (firstOrCreate / idempotent upsert).

---

## 6. Effective dating & snapshots

* Config rows that affect money or entitlements carry `effective_from` / `effective_to` (nullable end = open-ended).
* Resolution uses entity/branch specificity + effective date + priority (same pattern as Phase 11 `AccountResolverService`).
* Posted payroll items, tax breakdowns, PF postings, and approved adjustments freeze resolved rates into `snapshot_json` (or equivalent). Later config edits never alter posted history.

---

## 7. Fiscal years

* Reuse / align with Phase 11 fiscal year definitions where possible (`fiscal_years` / entity fiscal calendar).
* Leave entitlements, tax YTD, PF interest periods, and payroll YTD opening balances reference `fiscal_year_id`.
* Fiscal-year start month is configurable per withholding scheme / leave policy set — not assumed to be January or July.

---

## 8. Formula engine

* Pay components may use `calculation_type = formula` with a sandboxed expression evaluator (no `eval`).
* **Status:** Partial — enum accepted; expression evaluation currently rejected at save/calculate until a safe parser ships (see gaps).
* Formula variables are whitelisted (component codes, attendance minutes, leave days, etc.) — never arbitrary PHP/SQL.

---

## 9. Numbering sequences

Expense vouchers, payroll runs, payslips, advances, loans, PF withdrawals, requisitions, and similar documents use Phase 11 `DocumentNumberService` sequences — never ad-hoc counters.

---

## 10. Approvals & workflow

* Configurable approval policies (amount thresholds, roles, PIN, manager chain).
* `use_workflow_engine` hooks reserved for Phase 29; fallback is configurable limit + role/PIN (Phase 11 manual-journal pattern).
* Approval does not imply GL post unless configured; payroll **Post** is the default accounting trigger.

---

## 11. Notifications

* Prefer Phase 14 notification abstraction when available; otherwise queued mail.
* Templates and channels are configurable (payslip ready, leave approved, payroll confirmed, advance disbursed, etc.).

---

## 12. Historical import framework (contract)

Shared behaviour for all module imports (details and column maps in [historical-migration.md](./historical-migration.md)):

1. Import **session** with status: `draft` → `validated` → `committed` / `failed` / `cancelled`.
2. Row-level validation with error report; commit only after validation (or dry-run).
3. Migrated financial history is **immutable** (`source = migration`); corrections via reversal / adjustment documents, not silent edits.
4. Sessions are **retryable** after fixing data; idempotent keys prevent duplicate commits.
5. Reconciliation reports compare imported totals to source file / control totals.
6. All commits are audit logged.

---

## 13. RBAC

* Permissions are Spatie permissions, seeded and editable per tenant.
* Default roles (HR Manager, Payroll Officer, Line Manager, Employee, Accountant) are seeded but fully editable.
* Module docs list their permission strings; [module-registry.md](./module-registry.md) is the index of gates.

---

## 14. Services boundary rule

```text
Phase 12 Service → AccountingEventService.publish(...)
                 ✗ JournalService / PostingRuleEngine / Account models (write path)
```

Cross-module coupling inside HRMS is via domain services and events — not shared mutable globals.

---

## 15. Configuration philosophy

See also [configuration-framework.md](./configuration-framework.md).

* Country packs and entity overrides are data.
* Approval policies, numbering, notification templates, holiday calendars, and formula expressions are data.
* Provider interfaces (`TaxWithholdingMethod`, `AttendanceSourceProvider`, future `StatutorySchemeCalculator`, `ProvidentFundInterestMethod`) are the only code extension points for jurisdiction-specific behaviour.
