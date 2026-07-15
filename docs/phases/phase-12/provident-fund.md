# Phase 12 — Provident Fund

**Gate / registry key:** `provident_fund`  
**Wave:** 3  
**Depends on:** `payroll`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Configurable provident / pension fund: employee/employer contributions, interest, withdrawals, settlement, and policies — no hardcoded contribution rates or withdrawal rules.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `pf.view` | View balances |
| `pf.manage` | Configure schemes / enroll |
| `pf.approve-withdrawal` | Approve withdrawals |
| `pf.post` | Post interest/settlement events |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-PF-FR-001 | Planned | PF schemes: code, entity, employee_rate, employer_rate, basis (basic/gross/component), wage_ceiling, effective dating. |
| P12-PF-FR-002 | Planned | Employee PF accounts with enrollment date, status, opening balance. |
| P12-PF-FR-003 | Planned | Contributions generated from payroll run lines and/or posted via `provident_fund.contribution_posted`. |
| P12-PF-FR-004 | Planned | Interest method is pluggable (`ProvidentFundInterestMethod`) with effective-dated rate tables — not hardcoded. |
| P12-PF-FR-005 | Planned | Interest runs produce immutable postings + `provident_fund.interest_posted`. |
| P12-PF-FR-006 | Planned | Withdrawal policies: reason codes, min service months, max % of balance, taxability flag, approval. |
| P12-PF-FR-007 | Planned | Withdrawals publish `provident_fund.withdrawal_posted` (context, not accounts). |
| P12-PF-FR-008 | Planned | Exit settlement closes account, posts `provident_fund.settlement_posted`, links to final payroll if configured. |
| P12-PF-FR-009 | Planned | Ledger of contributions, interest, withdrawals with running balance. |
| P12-PF-FR-010 | Planned | Historical PF balances and transactions importable (immutable). |
| P12-PF-FR-011 | Planned | ESS view own PF balance and request withdrawal (policy permitting). |

---

## 4. Domain model

```text
provident_fund_schemes
- id, code, name, legal_entity_id, employee_rate, employer_rate,
  contribution_basis, wage_ceiling nullable,
  account_mapping keys (asset/payable/expense),
  effective_from / to, status

provident_fund_accounts
- id, employee_id, scheme_id, enrolled_on, status,
  opening_balance, opening_as_of, timestamps

provident_fund_interest_rates
- id, scheme_id, annual_rate, method_code, effective_from / to

provident_fund_interest_runs
- id, scheme_id, period_start, period_end, status, accounting_event_id nullable

provident_fund_transactions
- id, account_id, txn_type (contribution_ee|contribution_er|interest|withdrawal|settlement|opening),
  amount, payroll_run_id nullable, reference, balance_after, source,
  is_immutable boolean, timestamps

provident_fund_withdrawal_policies
- id, scheme_id, reason_code, min_service_months, max_percent,
  requires_approval, taxable, effective_from / to, status

provident_fund_withdrawals
- id, account_id, policy_id, amount, reason, status, approved_by,
  accounting_event_id nullable, timestamps
```

---

## 5. Services & interfaces

```text
ProvidentFundService
interface ProvidentFundInterestMethod
PfPayrollBridge
```

---

## 6. Domain events

```text
provident_fund.contribution_posted
provident_fund.withdrawal_posted
provident_fund.settlement_posted
provident_fund.interest_posted
```

Mapping keys: `provident_fund_payable`, `provident_fund_expense`, `provident_fund_asset`.

---

## 7. Configurability surface

* Rates, basis, interest method, withdrawal policies — all config; country packs as data.

---

## 8. Historical migration inputs

* Opening balances; historical contribution/withdrawal ledgers.

---

## 9. Reports / ESS touchpoints

* PF register, interest certificate; ESS balance.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-PF-AC-001 | Planned | Contribution rates change via scheme effective dating without code deploy. |
| P12-PF-AC-002 | Planned | Withdrawal exceeding policy max % rejected. |
| P12-PF-AC-003 | Planned | Interest run is idempotent per scheme/period. |
| P12-PF-AC-004 | Planned | PF services do not write journals directly. |
| P12-PF-AC-005 | Planned | Migrated opening balance is immutable; corrections via adjustment txn. |

---

## 11. Out of scope / deferred hooks

* External trustee portals — integration Phase 15.  
* Exact Pakistan EOBI vs private PF legal filing — configuration, not encoded law.
