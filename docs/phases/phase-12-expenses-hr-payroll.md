# Phase 12 — Expense Management & HR / Payroll

**SRS Reference:** §3.12, §3.13
**Status:** Implemented (formula pay-component evaluator deferred; Phase 29 workflow engine is a stub hook)
**Depends on:** Phase 11 (Accounting event + posting-rule engine), Phase 7 (POS, for one attendance source), Phase 1 (RBAC), Phase 3 (branches / legal entities)
**Related future phases:** Phase 23 (Module Config Engine), Phase 26 (Employee mobile app), Phase 29 (Workflow Engine)

---

## 1. Objective

Deliver operating-expense management (one-off and recurring), HR (employees, attendance, leave, overtime), and payroll, in a way that is **fully configurable per tenant** and **loosely coupled** from every other module — especially Accounting.

No expense category, salary component, overtime threshold, leave rule, tax slab, statutory contribution, GL account, approval limit, or numbering scheme may be hardcoded in business logic. Everything is resolved at runtime from configuration that a tenant can edit without a code deployment.

---

## 2. Core Principles (non-negotiable)

* **No direct journals.** Expense, payroll, and any HR module MUST NOT create `journal_entries` / `journal_transactions` directly. They publish accounting events; the Phase 11 `PostingRuleEngine` + `AccountResolverService` resolve accounts and build lines. There is exactly one GL writer in the system, and it lives in Phase 11.
* **No hardcoded accounts.** Every GL account is resolved through an `account_mappings` key. Phase 12 introduces new keys (§7) but never references an account ID.
* **No hardcoded rules.** Overtime thresholds/multipliers, pay components, leave accrual, tax slabs, and statutory schemes live in effective-dated config tables and are editable in the admin UI.
* **Idempotent posting.** Every accounting event carries a deterministic idempotency key; reprocessing a payroll run, recurring-expense occurrence, or expense never double-posts (mirrors Phase 11 AC #5).
* **Module-gated.** Expenses, HR, Attendance, Leave, Overtime, Payroll, and Employee Self-Service are independently sellable and gated per branch/tenant (§13), consistent with the Phase 11 `AccountingModuleGate` and pending the Phase 23 registry.
* **Multi-entity / multi-currency aware.** Employees, expenses, and payroll runs belong to a legal entity with a functional currency. Foreign-currency expenses reuse Phase 11 `CurrencyConversionService`. Nothing assumes a single currency or entity.
* **Configurable numbering.** Expense vouchers, payroll runs, and payslips are numbered through the Phase 11 `DocumentNumberService` sequences — not ad-hoc counters.
* **Audit-logged.** Every create/approve/reject/post/reverse action is audit logged (reuse Phase 1 audit trail).

---

## 3. Integration with Accounting (the decoupling contract)

Phase 12 modules publish these events; the accounting module consumes them exactly like Phase 11's `sale.completed`:

```text
expense.posted            # a single approved expense
expense.recurring_due     # one occurrence of a recurring expense
expense.reversed
payroll.posted            # already declared in Phase 11 §3
payroll.approved          # optional: post on approval instead of generation
payroll.reversed
employee_advance.issued   # salary advance / loan disbursement
employee_advance.recovered
```

Each event payload carries **context, not accounts**: amounts, `branch_id`, `legal_entity_id`, `currency_code`, `expense_category_id` / `pay_component_id`, `party` (employee/vendor), `cost_centre_id`, `tax_type_id`, and `date`. The posting-rule set for the event resolves the debit/credit accounts via mapping keys.

Posting-rule sets are seeded as a configurable baseline (see §7) and are editable through the existing Phase 11 Posting Rules UI — Phase 12 adds no bespoke posting code.

**Idempotency keys** (same scheme as Phase 11):

```text
expense.posted:Expense:{id}
expense.recurring_due:RecurringExpenseOccurrence:{id}
payroll.posted:PayrollRun:{id}
employee_advance.issued:EmployeeAdvance:{id}
```

---

## 4. Expense Management

### 4.1 Tables

```text
expense_categories
- id
- code
- name
- parent_id nullable            # hierarchy
- account_mapping_key nullable  # overrides default expense_account mapping resolution
- is_group
- requires_receipt              # configurable, not global
- default_tax_type_id nullable
- status
- created_at / updated_at

expenses
- id
- expense_number                # via DocumentNumberService
- expense_category_id
- branch_id
- legal_entity_id
- cost_centre_id nullable
- vendor_party_type nullable / vendor_party_id nullable
- currency_code
- exchange_rate nullable
- amount                        # transaction currency
- tax_type_id nullable
- tax_amount
- functional_amount             # resolved at post time
- expense_date
- payment_method nullable       # resolves payment_method_account mapping
- description
- status                        # draft / pending_approval / approved / posted / reversed
- approval_required             # derived from policy (§4.3)
- approved_by nullable / approved_at nullable
- accounting_event_id nullable
- journal_entry_id nullable
- created_by / updated_by / timestamps

expense_attachments
- id
- expense_id
- disk                          # configurable storage (local / s3 / spaces) — not hardcoded
- path
- original_name / mime / size
- uploaded_by / created_at
```

### 4.2 Recurring Expenses

```text
recurring_expense_schedules
- id
- expense_category_id
- branch_id / legal_entity_id / cost_centre_id nullable
- currency_code
- amount
- tax_type_id nullable
- frequency                     # daily / weekly / monthly / quarterly / annual / custom_interval
- interval_count                # e.g. every 2 weeks
- day_of_period nullable        # e.g. 1st of month
- start_date / end_date nullable
- proration_policy              # none / first_period / last_period / both (configurable)
- next_run_at
- payment_method nullable
- status
- created_by / timestamps

recurring_expense_occurrences
- id
- recurring_expense_schedule_id
- period_key                    # e.g. 2027-03 — UNIQUE with schedule_id (idempotency)
- scheduled_for
- amount / functional_amount
- status                        # pending / posted / skipped / failed
- expense_id nullable
- accounting_event_id nullable
- created_at
```

A **unique constraint on `(recurring_expense_schedule_id, period_key)`** guarantees the daily scheduler cannot create two occurrences for the same period if the job runs twice.

### 4.3 Expense Rules (all configurable)

* Receipt requirement is per category (`requires_receipt`), not a global flag.
* Approval is driven by `expense_approval_policies` (§12), not a single hardcoded threshold.
* An approved expense publishes `expense.posted`; the posting rule resolves `expense_account` (from category mapping or `expense_default`), `input_tax` (if taxable), and the credit side (`accounts_payable`, `cash_on_hand`, `bank_account`, or `payment_method_account`).
* Foreign-currency expenses store both transaction and functional amounts; conversion via Phase 11 `CurrencyConversionService`.
* The recurring scheduler only *generates* occurrences and publishes events — it never posts a journal itself.

---

## 5. Employees & Attendance

### 5.1 Employees

```text
employees
- id
- employee_code                 # via DocumentNumberService
- user_id nullable              # optional link to an auth user
- legal_entity_id
- primary_branch_id
- salary_structure_id nullable
- hire_date / termination_date nullable
- employment_type               # full_time / part_time / contract / hourly (configurable list)
- default_cost_centre_id nullable
- payment_method / bank_details_encrypted nullable
- status
- timestamps
```

### 5.2 Attendance — source-agnostic

Attendance is **not** coupled to the POS PIN. It is captured through an `AttendanceSourceProvider` interface with pluggable drivers; POS PIN is just one driver.

```text
attendance_sources                # configured per tenant/branch
- id, driver (pos_pin | biometric | mobile | manual | import), config_json, status

attendance_records
- id
- employee_id
- branch_id
- source_id                     # which driver produced this
- clock_in / clock_out nullable
- worked_minutes                # derived
- status                        # open / closed / adjusted
- adjusted_by nullable / adjustment_reason nullable
- timestamps
```

New attendance drivers (e.g. a turnstile) are added by implementing the interface + a config row — no change to payroll or HR code.

---

## 6. Payroll — configurable component engine

Payroll is a **rule engine over pay components**, not a fixed salary formula.

### 6.1 Pay Components

```text
pay_components
- id
- code
- name
- type                          # earning / deduction / employer_contribution / statutory / reimbursement
- calculation_type              # fixed / percentage_of / formula / table_lookup
- basis_component_id nullable   # for percentage_of (e.g. HRA = 40% of basic)
- rate nullable
- formula_expression nullable   # safe expression evaluated in a sandbox
- taxable                       # affects tax base
- account_mapping_key           # e.g. payroll_expense, hra_expense, net_salary_payable
- effective_from / effective_to nullable
- legal_entity_id nullable
- status

salary_structures
- id, code, name, legal_entity_id nullable, status

salary_structure_components
- id, salary_structure_id, pay_component_id, amount_or_rate nullable, sequence
```

### 6.2 Statutory & Tax — table-driven per entity/country

```text
tax_slabs                        # income-tax brackets, e.g. Pakistan FBR slabs
- id, legal_entity_id, effective_from, lower_bound, upper_bound nullable,
  fixed_amount, marginal_rate, status

statutory_schemes                # EOBI, GPSSA/pension, gratuity, social security
- id, code, name, legal_entity_id, calculation_type, employee_rate, employer_rate,
  wage_ceiling nullable, account_mapping_key_employee, account_mapping_key_employer,
  effective_from / effective_to, status
```

Adding UAE GPSSA alongside Pakistan EOBI is two config rows + mapping keys — zero code.

### 6.3 Payroll Runs

```text
payroll_runs
- id, payroll_number (DocumentNumberService), legal_entity_id, branch_id nullable,
  period_start, period_end, currency_code, status (draft / pending_approval /
  approved / posted / reversed), totals_json, accounting_event_id nullable,
  journal_entry_id nullable, approved_by nullable, posted_by nullable, timestamps

payroll_items
- id, payroll_run_id, employee_id, gross, total_deductions,
  total_employer_contributions, net_pay, ytd_json, snapshot_json, timestamps

payroll_item_lines
- id, payroll_item_id, pay_component_id, component_snapshot_json, amount, sequence
```

`snapshot_json` freezes the resolved components/rates at run time so a later config change never retroactively alters a posted run.

### 6.4 Payroll Rules

* A run resolves each employee's components (structure + overtime + leave + statutory + tax) into `payroll_item_lines`, then aggregates.
* Generation produces a **draft**; posting requires approval per policy (§12) or the Phase 29 workflow hook.
* On post, the run publishes `payroll.posted` (idempotent). The posting rule resolves component account mappings for the debit side and `net_salary_payable` / `tax_withheld_payable` / statutory payables for the credit side.
* Reversal publishes `payroll.reversed`, mapping to a Phase 11 reversal journal — no manual GL edits.

---

## 7. Account Mapping Keys introduced by Phase 12

Seeded as a baseline, all editable via the Phase 11 Account Mappings UI:

```text
expense_default
expense_<category_code>          # optional per-category overrides
payroll_expense
overtime_expense
employer_contribution_expense
net_salary_payable
tax_withheld_payable
statutory_payable_<scheme_code>
employee_advance_receivable
reimbursement_payable
```

Posting-rule sets seeded for: `expense.posted`, `expense.recurring_due`, `payroll.posted`, `employee_advance.issued`. Seeder uses `firstOrCreate`, plus an idempotent upsert migration so existing installs receive the new rule sets/keys.

---

## 8. Leave Management (configurable)

```text
leave_types                      # seeded baseline, editable (Annual/Sick/Unpaid/etc.)
- id, code, name, is_paid, affects_payroll, status

leave_policies                   # the configurable rules, per type/entity
- id, leave_type_id, legal_entity_id nullable, accrual_method
  (fixed_annual / monthly_accrual / per_worked_hours), accrual_rate,
  max_balance nullable, carry_forward_limit nullable, carry_forward_expiry_months
  nullable, proration_on_join, effective_from / effective_to, status

leave_entitlements
- id, employee_id, leave_type_id, fiscal_year_id, accrued_days, used_days,
  carried_forward_days, remaining_days (derived)

leave_requests
- id, employee_id, leave_type_id, start_date, end_date, days, reason,
  status (pending / approved / rejected / cancelled), approval_chain_json, timestamps
```

Unpaid / balance-exceeding leave feeds the payroll run as a deduction component — the coupling is via a resolved component, not a hardcoded salary reduction.

---

## 9. Overtime Engine (configurable — no baked-in numbers)

```text
overtime_policies
- id, legal_entity_id nullable, branch_id nullable,
  daily_threshold_minutes, weekly_threshold_minutes,
  rest_day_applies, public_holiday_applies,
  effective_from / effective_to, status, priority

overtime_multipliers
- id, overtime_policy_id, day_type (weekday / weekend / rest_day / public_holiday),
  multiplier

overtime_records
- id, employee_id, date, regular_minutes, overtime_minutes, day_type,
  resolved_multiplier, overtime_policy_id, approved_by nullable, status
```

The engine resolves the most specific active policy (entity/branch/effective-date, same specificity approach as the Phase 11 `AccountResolverService`). Thresholds and multipliers come from these tables only. Approved overtime becomes an `overtime_expense` pay component in the run.

---

## 10. Payslips & Employee Self-Service

* Payslip PDF per `payroll_item` (gross breakdown, deductions, employer contributions, net, YTD) generated via the Phase 11/pdf tooling; storage disk configurable.
* Email on payroll confirmation; bulk send for HR — delivery channel configurable (reuse Phase 14 notification abstraction when available; degrade to queued mail otherwise).
* Self-service scope (payslips, attendance, leave balance, leave requests). Full UI is Phase 26; Phase 12 exposes the read/write services and gates them behind `employee_self_service`.

---

## 11. Services & Module Boundaries

Independent, reusable services (payroll/expense publish events, never write GL):

```text
ExpenseService
RecurringExpenseScheduler           # generates occurrences, publishes events
AttendanceService                   # + AttendanceSourceProvider interface & drivers
LeaveService
OvertimeEngine
PayrollCalculationService           # component resolution
PayrollRunService                   # lifecycle + event publish
PayslipService
StatutoryResolverService            # tax slabs + statutory schemes
EmployeeAdvanceService
```

These depend on Phase 11 `AccountingEventService` **only** by publishing events. They must not import `JournalService`, `PostingRuleEngine`, or any account model.

---

## 12. Approval Policies (configurable)

```text
expense_approval_policies
- id, branch_id nullable, expense_category_id nullable, legal_entity_id nullable,
  min_amount, requires (pin / manager / workflow), approver_role nullable,
  effective_from / effective_to, priority, status

payroll_approval_settings          # per tenant/entity, mirrors Phase 11 manual_journal_approval_limit
- id, legal_entity_id, requires_approval, approval_limit nullable,
  use_workflow_engine (Phase 29 hook)
```

Fallback (no workflow module) is a configurable limit + PIN/role check — the same pattern Phase 11 uses for manual journals.

---

## 13. Module Gating

`config/hr_payroll_modules.php` dependency graph (mirrors `config/accounting_modules.php`):

```text
expenses            => requires [] (accounting posting optional)
hr                  => requires []
attendance          => requires [hr]
leave               => requires [hr]
overtime            => requires [hr, attendance]
payroll             => requires [hr]
employee_self_service => requires [hr]
```

Gated per branch via the existing branch profile mechanism + an `EnsureModuleEnabled` middleware; nav visibility driven by an `enabledHrModules` Inertia prop. Swappable for the Phase 23 registry by replacing the gate binding only.

---

## 14. Permissions (configurable per tenant)

```text
expenses.view / create / approve / post / reverse / manage-categories / manage-recurring
hr.view-employees / manage-employees
attendance.view / record / adjust / manage-sources
leave.view / request / approve / manage-types / manage-policies
overtime.view / approve / manage-policies
payroll.view / process / approve / post / reverse / manage-components / manage-structures
payroll.manage-statutory / manage-tax-slabs
selfservice.view-own
```

Default roles (HR Manager, Payroll Officer, Line Manager, Employee, Accountant) seeded but fully editable.

---

## 15. Acceptance Criteria

**Decoupling / configurability (the point of this rewrite):**

1. No Phase 12 service references a GL account ID or constructs a journal line; grep for `JournalService` / `journal_transactions` in `app/Services/*Hr*`, `*Payroll*`, `*Expense*` returns nothing.
2. Changing an overtime multiplier or threshold via config changes the next payroll run's output with **no code deployment**.
3. Adding a new statutory scheme (e.g. UAE GPSSA) is config + mapping only; it posts to the correct payables without new posting code.
4. Adding a new attendance driver requires only implementing `AttendanceSourceProvider` + a config row; payroll/leave code is untouched.
5. A branch with `payroll` disabled but `expenses` enabled hides payroll nav and rejects payroll routes, while expenses work.

**Functional:**

6. An approved expense above policy threshold is blocked until approval, then publishes `expense.posted` and posts the resolved journal.
7. A recurring rent expense generates exactly one occurrence per period and auto-posts; running the scheduler twice for the same period creates no duplicate (unique `period_key`).
8. A payroll run debits resolved expense components and credits net-salary, tax-withheld, and statutory payables in one balanced accounting transaction.
9. Reprocessing the same payroll run creates no duplicate journal (idempotency key).
10. A foreign-currency expense stores transaction and functional amounts and posts at the resolved rate.
11. Leave approval updates entitlement `used_days`; unpaid/over-balance leave reduces net pay via a deduction component.
12. Unapproved overtime is excluded; approved overtime is calculated at the resolved multiplier from `overtime_policies`.
13. Payslip PDF totals match the `payroll_item` and its lines exactly.
14. A posted payroll run reversal produces a linked Phase 11 reversal journal; no manual GL edits occur.
15. All Phase 12 create/approve/post/reverse actions are audit logged.


# Phase 12 Addendum — Configurable Payroll Withholding-Tax Engine

**Replaces / expands:** Phase 12 §6.2 (Statutory & Tax)
**Principle:** Pakistan FBR is *one configured method*, never the hardcoded engine. Any jurisdiction is expressed as (a) a withholding **method**, (b) effective-dated **brackets**, and (c) **method parameters** — all editable without a code deployment.

---

## 1. Why a table alone is not enough

Making only the slab table configurable would still bake Pakistan's *algorithm* — projected-annualized cumulative averaging with YTD self-correction — into the code. Other jurisdictions use different algorithms (periodic annualization, wage-bracket table lookup, flat rate). Configurability therefore lives at two layers: the **bracket data** and the **withholding method** that consumes it.

The tax engine's only output is a resolved `income_tax_withheld` deduction component (amount + transparent breakdown). It flows into the payroll run as a component line; the run publishes `payroll.posted`; accounting maps `tax_withheld_payable`. **The tax engine never imports an accounting class or writes the GL.**

---

## 2. Configuration model

### 2.1 Withholding schemes

```text
withholding_schemes
- id
- code                          # e.g. PK-FBR-SALARY, GENERIC-ANNUAL, US-PERCENTAGE
- name
- legal_entity_id nullable      # scheme resolved per entity (multi-country tenants)
- jurisdiction_code             # PK, AE, GB, US, ...
- method                        # projected_annualized_cumulative | periodic_bracket
                                #   | flat_rate | wage_bracket_table | custom
- fiscal_year_start_month       # 7 for Pakistan (July); 1 elsewhere
- currency_code
- rounding_mode                 # none | nearest_1 | nearest_10
- remainder_absorption          # last_period | none
- tax_base_mode                 # full_month | prorated   (full_month = SAP/Oracle)
- min_tax_amount nullable
- effective_from / effective_to nullable
- priority
- status
```

### 2.2 Brackets (generalized; holds FBR slabs or any country's table)

```text
tax_brackets
- id
- withholding_scheme_id
- sequence
- threshold                     # amount ABOVE which marginal_rate applies
                                #   (= top of previous bracket, e.g. 1200000)
- fixed_amount                  # cumulative tax up to `threshold`
- marginal_rate                 # decimal, e.g. 0.11
- effective_from / effective_to nullable
- status
```

Tax on an amount `x`: pick the highest bracket whose `threshold <= x`, then
`tax = fixed_amount + (x - threshold) * marginal_rate` (all via bcmath).

> FBR 11% bracket → `threshold=1200000, fixed_amount=6000, rate=0.11`.
> `x=1,422,000` → `6000 + (1,422,000 − 1,200,000)×0.11 = 6000 + 24,420 = 30,420`.
> Defining by threshold (not `1,200,001`) removes the ±1-rupee drift.

### 2.3 Method parameters (typed columns above + optional JSON for method-specific knobs)

`wage_bracket_table` adds `pay_frequency` + `filing_status` dimensions to
`tax_brackets` via a nullable discriminator column; `flat_rate` uses a single
bracket with `threshold=0`. No schema change per country.

### 2.4 YTD opening balances (mandatory for correctness)

```text
payroll_ytd_opening_balances
- id
- employee_id
- fiscal_year_id
- withholding_scheme_id
- opening_taxable_gross         # earned before go-live / before system onboarding
- opening_tax_withheld
- opening_months_elapsed        # months already run outside the system this FY
- source                        # migration | manual | prior_system
- created_by / created_at
```

YTD used by the engine = `opening_* + sum(processed payroll items this FY)`.

---

## 3. Engine contract (pluggable strategy)

```text
interface TaxWithholdingMethod
    compute(TaxContext): TaxResult

TaxContext
- employee, period (year, month index)
- current_taxable_gross         # per tax_base_mode (full_month by default)
- ytd_taxable_gross             # opening + processed
- ytd_tax_withheld              # opening + processed
- months_remaining_incl_current # (fiscal_year_end_month − current) + 1
- fiscal_year, brackets (resolved, effective-dated), scheme (params)
- hire_date

TaxResult
- period_tax_withheld
- projected_annual, annual_tax, effective_bracket
- breakdown[]                   # every step, for payslip transparency + snapshot
```

`PayrollCalculationService` calls the method, receives `period_tax_withheld`, and
emits it as the `income_tax_withheld` component line. `breakdown` is frozen into the
payroll item `snapshot_json`, so a later slab/param change never alters a posted month.

---

## 4. Method: `projected_annualized_cumulative` (Pakistan FBR, and PAYE-like regimes)

```
R  = months_remaining_incl_current            # short tenure is automatic
projected_annual = ytd_taxable_gross + current_taxable_gross * R
annual_tax       = brackets(projected_annual)          # §2.2
remaining_tax    = annual_tax - ytd_tax_withheld
period_tax       = round( remaining_tax / R , rounding_mode )
# final period of the FY (or last processed month): period_tax = remaining_tax  (absorb remainder)
```

`current_taxable_gross` is the **full monthly salary** when `tax_base_mode=full_month`
(gross *pay* is prorated separately as its own component; the tax base is not).

This single formula reproduces every reference case (see §6): standard 12-month,
short-tenure joiner, mid-year salary change (probation→permanent), and out-of-order
self-correction — because projection is rebuilt each month from actual YTD + current
base × remaining months.

---

## 5. Correctness guardrails (mandatory — these prevent silent wrong tax)

1. **Sequential processing enforced.** The engine refuses to process a period for an
   employee/FY unless every prior period since hire (or since `opening_months_elapsed`)
   is posted. Out-of-order processing is the documented failure that silently
   undercollects (the "April at PKR 370" case: 711,000 projection vs 1,422,000
   reality, −29,310). Reject, don't guess.
2. **Opening YTD required for mid-year onboarding.** An employee migrated in April with
   3 prior months elsewhere must carry `opening_taxable_gross / opening_tax_withheld /
   opening_months_elapsed=3`, or the run is blocked. This is the payroll analogue of
   Phase 11 opening balances.
3. **All money is bcmath on decimal strings.** No floats (the source worked examples
   drift, e.g. 30,419.89). Rates stored as decimals; multiply/divide via bcmath.
4. **Threshold-based excess** (over the previous bracket's ceiling), never
   `lower_bound − 1`.
5. **Remainder absorption** in the final period so the FY total reconciles exactly to
   `annual_tax` (assert `sum(period_tax) == annual_tax` for a full, unchanged FY).
6. **Snapshot immutability.** Posted months store the resolved breakdown; slab/param
   edits are effective-dated and never retroactive.
7. **Scheme resolution** by `legal_entity_id` + effective date + priority (same
   specificity approach as Phase 11 `AccountResolverService`), so a multi-country
   tenant runs PK-FBR for its Pakistan entity and a different scheme elsewhere with no
   code branch.

---

## 6. Acceptance criteria (encode the reference cases as tests)

**Configurability**
1. Adding a new country = one `withholding_schemes` row + `tax_brackets` rows +
   (if the method exists) zero code. Prove with a `flat_rate` and a
   `periodic_bracket` scheme added by data only.
2. Changing an FBR slab is new effective-dated `tax_brackets` rows; prior posted
   months are unchanged (snapshot).
3. `tax_base_mode` toggled full_month↔prorated changes a mid-month joiner's tax with
   no code edit (Case 1B: full_month → PKR 2,000; prorated → PKR 0).
4. No tax-engine class imports `JournalService`/`PostingRuleEngine`/GL models.

**PK-FBR correctness (exact figures)**
5. Fatima, joins Jan, 237,000/mo → Jan tax **5,070**; April (Jan–Mar posted) → **5,070**.
6. Standard July joiner, 250,000/mo → **25,000**/mo, FY total **300,000**.
7. Short-tenure Oct joiner, 250,000/mo → projected **2,250,000**, **14,167**/mo,
   June absorbs remainder, FY total **127,500**.
8. Probation→permanent (Maryam, 150,000 Aug–Oct then 244,000): Aug **5,045**,
   Nov **18,415** (projected **2,402,000**, not 2,684,000), FY total **162,460**.
9. Out-of-order guard: processing April with Jan–Mar missing and **no opening balance
   is rejected**, not silently computed at 370.
10. With `opening_months_elapsed=3, opening_taxable_gross=711,000,
    opening_tax_withheld=15,210`, April computes **5,070** correctly.