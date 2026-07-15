# Phase 12 — Historical Data Migration Framework

**Gate / registry key:** Cross-cutting (used by all modules)  
**Wave:** 4  
**Depends on:** Import/export framework (SRS §3.18), Phase 11 pattern for openings  
**Status (module roll-up):** Partial  
**Follows:** [architecture.md](./architecture.md) §12

---

## 1. Objective

First-class ability to import opening balances and historical HR/payroll data with validation, reconciliation, immutability, retryable sessions, and audit — NFR-MIG.

Tax YTD openings (`payroll_ytd_opening_balances`) are Implemented/Partial as part of [tax-engine.md](./tax-engine.md). Broader suite is Planned.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `hrms.import` / `hrms.import-commit` | Validate & commit |
| Module manage perms | Per-entity type |

---

## 3. Functional requirements

| ID | Status | Statement |
| :---: | :--- | :--- |
| P12-MIG-FR-001 | Planned | Import sessions: draft → validated → committed / failed / cancelled. |
| P12-MIG-FR-002 | Planned | Row-level validation errors with downloadable report; commit blocked until clean (or explicit allow-partial policy). |
| P12-MIG-FR-003 | Planned | Committed migrated financial/history rows marked `source=migration` and **immutable**. |
| P12-MIG-FR-004 | Planned | Sessions retryable; idempotent keys prevent duplicate commits of same source row. |
| P12-MIG-FR-005 | Planned | Reconciliation report: row counts, sum checks vs control totals. |
| P12-MIG-FR-006 | Planned | All commits audit logged. |
| P12-MIG-FR-007 | Partial | YTD tax openings supported ([tax-engine.md](./tax-engine.md)). |
| P12-MIG-FR-008 | Planned | Supported entity types (handlers): |

**Handlers (each module defines columns):**

| Entity type | Module SRS | Notes |
| :--- | :--- | :--- |
| employees | employees.md | Masters |
| departments / designations / grades | org docs | Masters |
| attendance_records | attendance.md | History |
| leave_entitlements / leave_requests | leave*.md | Openings + history |
| payroll_history | payroll-core.md | Immutable payslips/items |
| payroll_ytd_openings | tax-engine.md | Partial today |
| provident_fund | provident-fund.md | Balances + txns |
| employee_loans / advances | loans/advance | Open balances |
| expenses / reimbursements | expenses/reimbursements | History |
| payroll_adjustments | payroll-adjustments.md | Arrears/bonuses |
| appraisal_results | appraisal.md | Finalized only |
| holiday_calendars | holiday-calendar.md | Dates |

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-MIG-FR-009 | Planned | Prefers existing Import/Export wizard mechanics (`docs/generic-import-export.md` patterns). |
| P12-MIG-FR-010 | Planned | Dry-run mode without writes. |

---

## 4. Domain model

```text
hrms_import_sessions
- id, entity_type, legal_entity_id, status, file_disk, file_path,
  control_totals_json, result_json, created_by, committed_at, timestamps

hrms_import_rows
- id, session_id, row_number, payload_json, status, error_messages_json,
  target_type nullable, target_id nullable, source_row_hash

# target records set is_migrated / source=migration where applicable
```

---

## 5. Services & interfaces

```text
interface HrmsImportHandler
  validate(row): errors
  commit(row, session): target
HrmsImportSessionService
```

---

## 6. Domain events

```text
hrms_import.validated
hrms_import.committed
hrms_import.failed
```

---

## 7. Configurability surface

* Column maps, required fields, control-total rules per handler — config/handler code, not scattered controllers.

---

## 8. Historical migration inputs

* This document *defines* them.

---

## 9. Reports / ESS touchpoints

* Reconciliation + exception reports only (admin).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-MIG-AC-001 | Partial | Mid-year employee with tax openings computes correct April tax (see tax ACs). |
| P12-MIG-AC-002 | Planned | Re-commit same source_row_hash does not duplicate employee. |
| P12-MIG-AC-003 | Planned | Attempt to update migrated immutable payroll_history row is rejected. |
| P12-MIG-AC-004 | Planned | Session reconciliation sum(net_pay) matches control total or fails commit. |
| P12-MIG-AC-005 | Planned | Failed session can be retried after fixing file without orphan partial commits (or orphans clearly marked). |

---

## 11. Out of scope / deferred hooks

* Live sync from external HRMS APIs — Phase 15 connectors; batch import is in-scope.
