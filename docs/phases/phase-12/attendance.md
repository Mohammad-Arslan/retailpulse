# Phase 12 — Attendance Management

**Gate / registry key:** `attendance`  
**Wave:** 2  
**Depends on:** `hr`  
**Status (module roll-up):** Implemented  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Capture and adjust employee attendance through source-agnostic providers (POS PIN, biometric, mobile, manual, import). Attendance is not coupled to POS internals beyond an optional provider driver.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `attendance.view` | View records |
| `attendance.record` | Clock / create |
| `attendance.adjust` | Adjust times with reason |
| `attendance.manage-sources` | Configure providers |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-ATT-FR-001 | Implemented | Attendance shall be captured via `AttendanceSourceProvider` interface with pluggable drivers. |
| P12-ATT-FR-002 | Implemented | Supported baseline drivers: `pos_pin`, `biometric`, `mobile`, `manual`, `import` (config rows enable them). |
| P12-ATT-FR-003 | Implemented | POS PIN is one driver only; payroll/leave must not import POS Pin services directly. |
| P12-ATT-FR-004 | Implemented | Records store employee, branch, source, clock_in, clock_out, worked_minutes, status. |
| P12-ATT-FR-005 | Implemented | Status lifecycle: open / closed / adjusted. |
| P12-ATT-FR-006 | Implemented | Adjustments require actor + reason; audit logged. |
| P12-ATT-FR-007 | Planned | Late / early / absence flags resolved against shift assignment when `shifts_roster` enabled. |
| P12-ATT-FR-008 | Planned | Configurable grace minutes and break rules per shift/policy. |
| P12-ATT-FR-009 | Planned | Daily attendance summary feed for payroll (worked minutes, OT candidate minutes). |
| P12-ATT-FR-010 | Implemented | Historical attendance import via the generic import/export framework (`attendance` entity, `AttendanceImportHandler`/`AttendanceExportHandler`, registered in `ImportExportRegistry` alongside Employees/Departments/etc.). Writes directly to `attendance_records` flagged `is_historical`, bypassing the live `AttendanceService` provider pipeline (same "no live side effects" pattern as historical sales import). Permissions: `attendance.import`, `attendance.export`. |
| P12-ATT-FR-011 | Implemented | New drivers require interface + config row only — no payroll code changes. |

---

## 4. Domain model

```text
attendance_sources
- id, driver (pos_pin | biometric | mobile | manual | import),
  config_json, branch_id nullable, status, timestamps

attendance_records
- id
- employee_id
- branch_id
- source_id
- clock_in / clock_out nullable
- worked_minutes
- status                          # open / closed / adjusted
- is_historical                   # true for rows created via the historical import handler
- adjusted_by nullable / adjustment_reason nullable
- timestamps

# Planned
attendance_policies
- id, legal_entity_id nullable, branch_id nullable,
  grace_in_minutes, grace_out_minutes, effective_from / to, status
```

---

## 5. Services & interfaces

```text
AttendanceService
interface AttendanceSourceProvider
  clockIn / clockOut / importBatch
PosPinAttendanceProvider              # Implemented
```

---

## 6. Domain events

```text
attendance.clocked_in
attendance.clocked_out
attendance.adjusted
```

No GL events.

---

## 7. Configurability surface

* Sources, driver config_json, policies, grace — all config.

---

## 8. Historical migration inputs

* employee_code, date, clock_in, clock_out, branch_code, source=import.

---

## 9. Reports / ESS touchpoints

* Daily attendance register; ESS own punches ([employee-self-service.md](./employee-self-service.md)).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-ATT-AC-001 | Implemented | Adding a driver = interface + config row; payroll untouched. |
| P12-ATT-AC-002 | Implemented | POS PIN clock-in creates attendance_record with source driver pos_pin. |
| P12-ATT-AC-003 | Implemented | Adjusted record stores reason and actor. |
| P12-ATT-AC-004 | Implemented | Module gate: attendance disabled hides routes. |

---

## 11. Out of scope / deferred hooks

* Biometric hardware SDKs — provider stubs only until Phase 21/integrations.  
* Full mobile punch UI — Phase 26.
