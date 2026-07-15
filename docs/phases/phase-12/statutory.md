# Phase 12 — Statutory Contributions

**Gate / registry key:** `statutory`  
**Wave:** 3  
**Depends on:** `payroll`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

Tax withholding is specified separately in [tax-engine.md](./tax-engine.md).

---

## 1. Objective

Table-driven employer/employee statutory schemes (EOBI, social security, GPSSA, gratuity accruals, etc.) — adding a country scheme is config + mapping keys, not new payroll code.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `payroll.manage-statutory` | CRUD schemes |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-STAT-FR-001 | Implemented | `statutory_schemes` store code, name, entity, calculation_type, employee_rate, employer_rate, wage_ceiling, mapping keys, effective dating. |
| P12-STAT-FR-002 | Implemented | Adding UAE GPSSA alongside Pakistan EOBI is two config rows + mappings — zero posting code. |
| P12-STAT-FR-003 | Implemented | Resolved amounts appear as component lines; employer side as employer_contribution. |
| P12-STAT-FR-004 | Planned | Pluggable `StatutorySchemeCalculator` for non-percentage methods (tiered, age-based) without changing PayrollRunService. |
| P12-STAT-FR-005 | Planned | Employee opt-in/opt-out and exemption flags with effective dates. |
| P12-STAT-FR-006 | Planned | Scheme assignment by entity/grade/employment_type rules. |
| P12-STAT-FR-007 | Planned | Historical YTD statutory openings for mid-year go-live. |

---

## 4. Domain model

```text
statutory_schemes
- id, code, name, legal_entity_id, calculation_type,
  employee_rate, employer_rate, wage_ceiling nullable,
  account_mapping_key_employee, account_mapping_key_employer,
  effective_from / effective_to, status

# Planned
statutory_scheme_assignments
- id, statutory_scheme_id, employee_id nullable, rule_json,
  effective_from / to, status

statutory_ytd_openings
- id, employee_id, fiscal_year_id, statutory_scheme_id,
  opening_employee_amount, opening_employer_amount, source
```

Legacy note: older `tax_slabs` table from early draft is superseded for income tax by [tax-engine.md](./tax-engine.md) brackets; statutory remains separate.

---

## 5. Services & interfaces

```text
StatutoryResolverService                 # Implemented
interface StatutorySchemeCalculator      # Planned
```

---

## 6. Domain events

Via `payroll.posted` → statutory payables mapping `statutory_payable_<scheme_code>`.

---

## 7. Configurability surface

* Rates, ceilings, calculation types, assignments — config; no hardcoded EOBI %.

---

## 8. Historical migration inputs

* Scheme catalogue; YTD openings.

---

## 9. Reports / ESS touchpoints

* Statutory remittance summaries (Planned).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-STAT-AC-001 | Implemented | New scheme via config posts to correct payable without new posting code. |
| P12-STAT-AC-002 | Implemented | Employer contribution totals on payroll_item. |
| P12-STAT-AC-003 | Planned | Wage ceiling caps contributory wages per scheme config. |

---

## 11. Out of scope / deferred hooks

* Government e-filing integrations — Phase 15 later; export files only when configured.
