# Phase 12 — Salary Advance

**Gate / registry key:** `salary_advance`  
**Wave:** 3  
**Depends on:** `payroll`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Issue and recover salary advances with configurable eligibility, limits, and payroll recovery — GL only via accounting events.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `advances.view` | View |
| `advances.issue` | Create/disburse |
| `advances.recover` | Manual recovery / schedule |
| `advances.approve` | Approve |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-ADV-FR-001 | Partial | Advances have number, employee, amount, currency, reason, status, recovery schedule. |
| P12-ADV-FR-002 | Partial | Issue publishes `employee_advance.issued` (idempotent); mapping `employee_advance_receivable`. |
| P12-ADV-FR-003 | Partial | Recovery publishes `employee_advance.recovered` and/or payroll deduction lines. |
| P12-ADV-FR-004 | Planned | Eligibility policies: max amount, max % of salary, grade limits, open-advance count, approval thresholds. |
| P12-ADV-FR-005 | Planned | Auto-create deduction assignments until balance cleared. |
| P12-ADV-FR-006 | Planned | Historical open advances importable. |
| P12-ADV-FR-007 | Planned | ESS request advance (policy permitting). |

Foundation: `EmployeeAdvanceService` and events declared; full policy engine Planned.

---

## 4. Domain model

```text
employee_advances
- id, advance_number, employee_id, legal_entity_id, amount, currency_code,
  reason, status (draft|pending_approval|issued|recovering|closed|cancelled),
  issued_at, accounting_event_id nullable, balance_remaining, timestamps

employee_advance_recoveries
- id, employee_advance_id, amount, recovery_date, payroll_run_id nullable,
  accounting_event_id nullable, timestamps

employee_advance_policies                   # Planned
- id, legal_entity_id, max_amount, max_percent_of_salary,
  max_open_count, grade_id nullable, requires_approval, effective_from / to
```

---

## 5. Services & interfaces

```text
EmployeeAdvanceService
```

---

## 6. Domain events

```text
employee_advance.issued
employee_advance.recovered
```

Keys: `employee_advance.issued:EmployeeAdvance:{id}`, `employee_advance.recovered:EmployeeAdvanceRecovery:{id}`

---

## 7. Configurability surface

* Limits, approval, recovery component code — config.

---

## 8. Historical migration inputs

* Open advances with remaining balance.

---

## 9. Reports / ESS touchpoints

* Advance ageing; ESS status.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-ADV-AC-001 | Partial | Issue does not call JournalService; publishes event. |
| P12-ADV-AC-002 | Planned | Policy max blocks issuance above limit. |
| P12-ADV-AC-003 | Planned | Payroll recovery reduces balance; closing when zero. |
| P12-ADV-AC-004 | Partial | Re-issue same advance id does not double-post (idempotency). |

---

## 11. Out of scope / deferred hooks

* Multi-currency advance FX policy — use Phase 11 conversion when amounts differ from functional.
