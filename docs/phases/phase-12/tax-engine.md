# Phase 12 — Income Tax & Statutory Tax Engine

**Gate / registry key:** `tax_engine`  
**Wave:** 3  
**Depends on:** `payroll`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

Ported from Phase 12 withholding-tax addendum. Pakistan FBR is **one configured method**, never the hardcoded engine.

---

## 1. Objective

Provide a provider-based payroll withholding engine: method + effective-dated brackets + parameters. Output is solely the `income_tax_withheld` deduction component (amount + breakdown). The engine never writes GL.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `payroll.manage-tax-slabs` | Manage schemes & brackets |
| `payroll.process` | Runs engine during calculation |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-TAX-FR-001 | Partial | Withholding schemes configure jurisdiction, method, FY start month, rounding, remainder absorption, tax_base_mode, currency, effective dating, priority. |
| P12-TAX-FR-002 | Partial | Methods: `projected_annualized_cumulative`, `periodic_bracket`, `flat_rate`, `wage_bracket_table`, `custom`. |
| P12-TAX-FR-003 | Partial | Brackets: threshold, fixed_amount, marginal_rate; tax = fixed + (x − threshold) × rate via bcmath. |
| P12-TAX-FR-004 | Partial | YTD openings required for mid-year onboarding (`payroll_ytd_opening_balances`). |
| P12-TAX-FR-005 | Partial | Interface `TaxWithholdingMethod.compute(TaxContext): TaxResult` with breakdown frozen into snapshot. |
| P12-TAX-FR-006 | Partial | `projected_annualized_cumulative` implements FBR-style formula (see §4). |
| P12-TAX-FR-007 | Partial | Sequential processing enforced; out-of-order without openings rejected. |
| P12-TAX-FR-008 | Partial | All money via bcmath decimal strings — no floats. |
| P12-TAX-FR-009 | Partial | Scheme resolution by legal_entity + effective date + priority. |
| P12-TAX-FR-010 | Partial | Engine does not import JournalService / PostingRuleEngine / GL models. |
| P12-TAX-FR-011 | Planned | Admin UI for schemes/brackets/openings fully at parity with table model (if Partial elsewhere). |
| P12-TAX-FR-012 | Planned | Additional jurisdiction packs as seeded data only. |

---

## 4. Domain model

```text
withholding_schemes
- id, code, name, legal_entity_id nullable, jurisdiction_code,
  method, fiscal_year_start_month, currency_code,
  rounding_mode, remainder_absorption, tax_base_mode,
  min_tax_amount nullable, effective_from / to, priority, status

tax_brackets
- id, withholding_scheme_id, sequence, threshold, fixed_amount,
  marginal_rate, effective_from / to, status
  # wage_bracket_table may add nullable pay_frequency / filing_status discriminators

payroll_ytd_opening_balances
- id, employee_id, fiscal_year_id, withholding_scheme_id,
  opening_taxable_gross, opening_tax_withheld, opening_months_elapsed,
  source (migration|manual|prior_system), created_by, created_at
```

### Method: `projected_annualized_cumulative`

```text
R  = months_remaining_incl_current
projected_annual = ytd_taxable_gross + current_taxable_gross * R
annual_tax       = brackets(projected_annual)
remaining_tax    = annual_tax - ytd_tax_withheld
period_tax       = round(remaining_tax / R, rounding_mode)
# final period: period_tax = remaining_tax when remainder_absorption = last_period
```

`tax_base_mode=full_month` uses full monthly salary as tax base (gross pay proration is separate).

---

## 5. Services & interfaces

```text
interface TaxWithholdingMethod
TaxContext / TaxResult
PayrollCalculationService          # calls method; emits income_tax_withheld line
```

---

## 6. Domain events

Tax is a payroll line; payable via `payroll.posted` → `tax_withheld_payable`.

---

## 7. Configurability surface

* Methods, brackets, FY month, rounding, openings — all config.

---

## 8. Historical migration inputs

* YTD openings mandatory for mid-year employees.

---

## 9. Reports / ESS touchpoints

* Tax breakdown on payslip; YTD tax report.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-TAX-AC-001 | Partial | New country = scheme + brackets (+ existing method) with zero code. |
| P12-TAX-AC-002 | Partial | Effective-dated bracket change does not alter posted month snapshots. |
| P12-TAX-AC-003 | Partial | tax_base_mode toggle changes mid-month joiner tax without code edit. |
| P12-TAX-AC-004 | Partial | No tax-engine class imports GL writers. |
| P12-TAX-AC-005 | Partial | Fatima, Jan join, 237,000/mo → Jan tax **5,070**; April (Jan–Mar posted) → **5,070**. |
| P12-TAX-AC-006 | Partial | July joiner 250,000/mo → **25,000**/mo, FY total **300,000**. |
| P12-TAX-AC-007 | Partial | Oct joiner 250,000/mo → **14,167**/mo, June absorbs remainder, FY **127,500**. |
| P12-TAX-AC-008 | Partial | Maryam probation→permanent: Aug **5,045**, Nov **18,415**, FY **162,460**. |
| P12-TAX-AC-009 | Partial | April without prior months and without openings is **rejected**. |
| P12-TAX-AC-010 | Partial | openings months=3, gross=711,000, tax=15,210 → April **5,070**. |

---

## 11. Out of scope / deferred hooks

* Corporate income tax / sales tax — Phase 11/14, not payroll withholding.  
* FBR IRIS filing — Phase 8/14 POS context; payroll withholding export is separate Planned export.
