# Phase 12 — Leave Fiscal Year

**Gate / registry key:** `leave`  
**Wave:** 2  
**Depends on:** `hr`  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Ensure leave entitlements, carry-forward, expiry, and encashment operate on configurable fiscal years (NFR-FY), not assumed calendar years.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `leave.manage-policies` | Configure FY alignment |
| `leave.view` | View FY balances |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-LVFY-FR-001 | Implemented | Leave entitlements are keyed by `fiscal_year_id`. |
| P12-LVFY-FR-002 | Partial | Fiscal years align with entity/Phase 11 fiscal definitions where available. |
| P12-LVFY-FR-003 | Planned | Year-end job: apply carry_forward_limit, expire excess, create next FY entitlements. |
| P12-LVFY-FR-004 | Planned | Carry-forward expiry_months starts from FY start or join — configurable. |
| P12-LVFY-FR-005 | Planned | Encashment at FY end optional per policy. |
| P12-LVFY-FR-006 | Planned | Opening leave balances on mid-year go-live are FY-specific migration rows. |
| P12-LVFY-FR-007 | Planned | Reports filter by fiscal year. |

---

## 4. Domain model

```text
# Uses Phase 11 / shared fiscal_years

leave_entitlements.fiscal_year_id          # Implemented

leave_year_end_runs                        # Planned
- id, legal_entity_id, fiscal_year_id, status, totals_json,
  executed_by, executed_at, timestamps

leave_year_end_lines                       # Planned
- id, leave_year_end_run_id, employee_id, leave_type_id,
  carried_forward, expired, encashed, next_opening
```

---

## 5. Services & interfaces

```text
LeaveFiscalYearService
  openEntitlementsForYear
  closeYear (carry/expire/encash)
```

---

## 6. Domain events

```text
leave.fiscal_year_closed
leave.carry_forward_applied
```

---

## 7. Configurability surface

* FY start, carry limits, expiry — from policies + fiscal calendar config.

---

## 8. Historical migration inputs

* Opening balances per employee/type/FY.

---

## 9. Reports / ESS touchpoints

* FY balance vs usage; ESS shows current FY.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-LVFY-AC-001 | Implemented | Entitlement rows are unique per (employee, leave_type, fiscal_year). |
| P12-LVFY-AC-002 | Planned | Year-end with carry_forward_limit=5 carries at most 5 days; excess expired or encashed per policy. |
| P12-LVFY-AC-003 | Planned | Mid-year opening migration creates entitlement for current FY only unless file supplies prior FY. |

---

## 11. Out of scope / deferred hooks

* Multi-calendar concurrent leave years for same employee — not required; one FY calendar per entity.
