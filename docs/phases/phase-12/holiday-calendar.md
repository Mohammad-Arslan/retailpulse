# Phase 12 — Holiday Calendar

**Gate / registry key:** `holiday_calendar`  
**Wave:** 1  
**Depends on:** `hr`  
**Status (module roll-up):** Implemented  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Support multiple configurable holiday calendars (national, regional, branch/entity) so leave counting, roster, overtime day-type, and payroll holidays are never hardcoded.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `hr.manage-org` / `holiday.manage` | CRUD calendars & days |
| `leave.view` / `attendance.view` | Read holidays for calculations |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-HOL-FR-001 | Implemented | The system shall support multiple holiday calendars per tenant with code, name, legal_entity_id nullable, branch_id nullable. |
| P12-HOL-FR-002 | Implemented | Each calendar contains holiday dates with name, type (public / optional / company), and is_paid flag. |
| P12-HOL-FR-003 | Implemented | Calendars are assigned to employees, branches, or entities with effective dating; most specific wins (employee > branch > entity > default). |
| P12-HOL-FR-004 | Planned | Leave day counting shall exclude or include holidays per leave policy configuration. |
| P12-HOL-FR-005 | Planned | Overtime engine day_type `public_holiday` shall resolve from assigned calendar. |
| P12-HOL-FR-006 | Planned | Roster generation shall mark holidays. |
| P12-HOL-FR-007 | Planned | Recurring annual holidays may be defined by month/day pattern for future years (optional generation job). |
| P12-HOL-FR-008 | Planned | Holidays are importable (calendar + dates). |

---

## 4. Domain model

```text
holiday_calendars
- id, code, name, legal_entity_id nullable, branch_id nullable, status, timestamps

holiday_dates
- id, holiday_calendar_id, holiday_date, name, holiday_type, is_paid, timestamps
- UNIQUE (holiday_calendar_id, holiday_date)

holiday_calendar_assignments
- id, holiday_calendar_id
- assignable_type / assignable_id   # entity | branch | employee
- effective_from / effective_to nullable
- priority
- status
```

---

## 5. Services & interfaces

```text
HolidayCalendarService
HolidayResolver
  isHoliday(employee|branch, date): ?HolidayDate
```

---

## 6. Domain events

```text
holiday_calendar.updated
```

Used by leave/OT recalculation listeners if needed.

---

## 7. Configurability surface

* All calendars, dates, types, assignment specificity — data only.

---

## 8. Historical migration inputs

* Calendar definitions and date lists for past/current fiscal years.

---

## 9. Reports / ESS touchpoints

* Holiday list by calendar year; ESS holiday view (Planned).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-HOL-AC-001 | Implemented | Employee on Branch A calendar sees Branch A holidays, not Branch B. |
| P12-HOL-AC-002 | Planned | Leave request spanning a public holiday excludes that day when policy says so. |
| P12-HOL-AC-003 | Planned | Overtime on a configured public holiday uses public_holiday multiplier. |
| P12-HOL-AC-004 | Implemented | Duplicate date in same calendar rejected. |

---

## 11. Out of scope / deferred hooks

* Religious floating holiday nomination workflows — optional later.
