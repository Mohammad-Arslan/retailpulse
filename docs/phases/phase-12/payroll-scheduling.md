# Phase 12 — Payroll Scheduling

**Gate / registry key:** `payroll`  
**Wave:** 3  
**Depends on:** `hr` / `payroll`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Define pay calendars, frequencies, cut-off dates, and scheduled generation of payroll runs so period boundaries are configuration-driven.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `payroll.manage-structures` / `payroll.manage-schedules` | CRUD calendars |
| `payroll.process` | Trigger/generate |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-PSCH-FR-001 | Planned | Pay calendars define frequency: monthly / biweekly / weekly / semi_monthly / custom. |
| P12-PSCH-FR-002 | Planned | Each period has period_start, period_end, cut_off_date, pay_date, status. |
| P12-PSCH-FR-003 | Planned | Employees / structures assign to a calendar. |
| P12-PSCH-FR-004 | Planned | Scheduler may auto-create draft payroll_runs for due periods (idempotent period_key). |
| P12-PSCH-FR-005 | Planned | Off-cycle runs allowed with reason and separate numbering sequence option. |
| P12-PSCH-FR-006 | Planned | Sequential processing guards (tax YTD) respect calendar order. |
| P12-PSCH-FR-007 | Planned | Fiscal year alignment for YTD uses scheme FY + calendar periods. |

---

## 4. Domain model

```text
pay_calendars
- id, code, name, legal_entity_id, frequency, status, timestamps

pay_periods
- id, pay_calendar_id, period_key, period_start, period_end,
  cut_off_date, pay_date, status (open|locked|closed),
  UNIQUE (pay_calendar_id, period_key)

payroll_run_schedules
- id, pay_calendar_id, auto_generate_drafts boolean, next_run_at, status
```

---

## 5. Services & interfaces

```text
PayCalendarService
PayrollScheduleJob
```

---

## 6. Domain events

```text
pay_period.opened
pay_period.locked
payroll_run.auto_draft_created
```

---

## 7. Configurability surface

* Frequencies, cut-offs, auto-draft flags — config.

---

## 8. Historical migration inputs

* Prior periods metadata if needed for sequential tax guards.

---

## 9. Reports / ESS touchpoints

* Upcoming pay dates on ESS (Planned).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-PSCH-AC-001 | Planned | Auto-job twice for same period_key creates one draft only. |
| P12-PSCH-AC-002 | Planned | Locked period rejects new attendance adjustments affecting payroll without reopen permission. |

---

## 11. Out of scope / deferred hooks

* Multi-country simultaneous calendars — supported via entity-scoped calendars only.
