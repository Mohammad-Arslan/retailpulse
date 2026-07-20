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
| P12-LVFY-FR-003 | Implemented | Year-end job (`leave:process-year-end`, scheduled daily) applies `carry_forward_limit`, expires excess, and resets the entitlement in place for the new year. **Deliberate deviation from the literal "create next FY entitlements" wording**: the rest of `LeaveService` (approve/cancel/request) always resolves the single `fiscal_year_id = null` entitlement bucket for an employee+leave type — creating a second row keyed to a real `fiscal_year_id` would silently orphan it from every future operation. Instead the existing bucket is reset in place (`accrued_days`/`used_days`/`encashed_days` → 0, `carried_forward_days` → computed carry) and the closing snapshot is preserved immutably in `leave_year_end_runs`/`leave_year_end_lines`. `fiscal_year_id` is still recorded on the run as a reference pointer in `fiscal_year` mode. |
| P12-LVFY-FR-004 | Implemented | `leave_policies.carry_forward_expiry_months` is stamped onto `leave_entitlements.carried_forward_expires_at` (`= period end + N months`) by `closeEntitlement()` whenever the policy sets it and something was actually carried, and is re-stamped fresh on every subsequent year-end close (never additive). `LeaveFiscalYearService::expireDueCarriedForward()`, called by the same daily `leave:process-year-end` job right after `processDue()`, reduces `carried_forward_days` by whatever's still unused once the stamped date is reached — capped at `remaining_days`, so days already consumed before expiry are never touched, only the unused remainder. Reuses the existing `leave_year_end_runs`/`leave_year_end_lines` reporting shape (a `CF-EXPIRY-{date}` period label per legal entity, same `(legal_entity_id, period_label)` idempotency guard). A policy with no `carry_forward_expiry_months` behaves exactly as before this change. |
| P12-LVFY-FR-005 | Implemented | Encashment at year-end is optional per policy (`leave_policies.year_end_excess_disposition`: `expire` default / `encash`) — only excess above `carry_forward_limit` is affected; days held by a pending leave request are never expired or encashed (see AC below). |
| P12-LVFY-FR-006 | Planned | Opening leave balances on mid-year go-live are FY-specific migration rows. |
| P12-LVFY-FR-007 | Planned | Reports filter by fiscal year. |

---

## 4. Domain model

```text
# Uses Phase 11 / shared fiscal_years (reference only — see FR-003)

leave_entitlements.fiscal_year_id          # Implemented (always null in practice today)
leave_entitlements.carried_forward_expires_at  # Implemented — nullable date, stamped by
                                                # closeEntitlement() when the policy's
                                                # carry_forward_expiry_months is set

leave_year_end_runs
- id, legal_entity_id, fiscal_year_id nullable, employee_id nullable (hire_anniversary mode),
  period_label (unique per legal_entity_id — e.g. "2026", "FY-12", "EMP-45-2026"),
  status, totals_json, executed_by nullable, executed_at, timestamps

leave_year_end_lines
- id, leave_year_end_run_id, employee_id, leave_type_id,
  carried_forward, expired, encashed, next_opening, timestamps
```

`HrEntitySetting.settings_json.default_leave_fiscal_year_mode` selects the trigger per legal entity:
- `calendar_year` (default) — triggers once a year on Jan 1 for the prior calendar year.
- `fiscal_year` — triggers per `FiscalYear` row for the entity once its `end_date` has passed.
- `hire_anniversary` — triggers per employee on their hire-date anniversary, for the year that just completed.

---

## 5. Services & interfaces

```text
LeaveFiscalYearService
  processDue(asOf)             # entry point called by leave:process-year-end; scans all active
                               # legal entities, resolves each one's mode, and closes any due period
  closeYear(...)               # per legal entity (or per employee in hire_anniversary mode);
                               # idempotent — guarded by the (legal_entity_id, period_label) unique index
  expireDueCarriedForward(asOf) # also called by leave:process-year-end, right after processDue();
                                # purely date-driven sweep of carried_forward_expires_at, no
                                # fiscal-year-mode branching needed (the date already encodes it)
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
| P12-LVFY-AC-002 | Implemented | Year-end with carry_forward_limit=5 carries at most 5 days; excess expired or encashed per policy. Days held by a `pending` leave request (`deduct_from_balance=true`) are excluded from the excess calculation entirely and always carry forward, so an approval after year-end can never find an insufficient balance because of year-end processing. |
| P12-LVFY-AC-003 | Planned | Mid-year opening migration creates entitlement for current FY only unless file supplies prior FY. |

---

## 11. Out of scope / deferred hooks

* Multi-calendar concurrent leave years for same employee — not required; one FY calendar per entity.
