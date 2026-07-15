# Phase 12 — Shift & Roster Management

**Gate / registry key:** `shifts_roster`  
**Wave:** 2  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Define shift templates and employee rosters so planned vs actual attendance, overtime thresholds, and rest days are configuration-driven.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `roster.view` | View shifts/rosters |
| `roster.manage` | CRUD shifts, publish rosters |
| `roster.assign` | Assign employees |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-SHF-FR-001 | Planned | The system shall support shift definitions: code, name, start_time, end_time, break_minutes, crosses_midnight flag, legal_entity/branch scope. |
| P12-SHF-FR-002 | Planned | Shifts may define weekly rest-day patterns (configurable). |
| P12-SHF-FR-003 | Planned | Roster periods (week/month) assign employee→shift→date. |
| P12-SHF-FR-004 | Planned | Roster publish workflow: draft → published; published roster is read-only except corrections with audit. |
| P12-SHF-FR-005 | Planned | Attendance late/early compares punches to rostered shift when module enabled. |
| P12-SHF-FR-006 | Planned | Overtime thresholds may reference shift length instead of fixed daily minutes when configured. |
| P12-SHF-FR-007 | Planned | Holiday calendar integration marks roster days as holiday. |
| P12-SHF-FR-008 | Planned | Clash detection: two shifts same employee overlapping times rejected. |
| P12-SHF-FR-009 | Planned | Roster import/export and historical roster import supported. |
| P12-SHF-FR-010 | Planned | ESS: view own roster (when ESS enabled). |

---

## 4. Domain model

```text
shifts
- id, code, name, legal_entity_id nullable, branch_id nullable,
  start_time, end_time, break_minutes, crosses_midnight,
  status, timestamps

shift_rest_day_rules
- id, shift_id, weekday (0-6) or pattern_json, timestamps

roster_periods
- id, branch_id, legal_entity_id, period_start, period_end,
  status (draft|published|archived), published_at nullable, timestamps

roster_assignments
- id, roster_period_id, employee_id, work_date, shift_id,
  UNIQUE (employee_id, work_date) unless split-shift policy allows multi with non-overlap
```

---

## 5. Services & interfaces

```text
ShiftService
RosterService
  draft / assign / publish / detectConflicts
RosterAttendanceBridge
```

---

## 6. Domain events

```text
roster.published
roster.assignment_changed
```

---

## 7. Configurability surface

* Shift times, rest days, publish rules, split-shift policy — config.

---

## 8. Historical migration inputs

* Shift catalogue; historical assignments for analytics.

---

## 9. Reports / ESS touchpoints

* Coverage report; ESS own schedule.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-SHF-AC-001 | Planned | Publishing roster freezes assignments; edit requires correction with audit. |
| P12-SHF-AC-002 | Planned | Overlapping assignments for same employee rejected. |
| P12-SHF-AC-003 | Planned | Attendance late flag uses rostered start + grace. |

---

## 11. Out of scope / deferred hooks

* Auto-optimization / AI rostering — out of scope.
