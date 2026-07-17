# Phase 12 — Overtime Engine

**Gate / registry key:** `overtime`  
**Wave:** 2  
**Depends on:** `hr`, `attendance`  
**Status (module roll-up):** Implemented  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Calculate and approve overtime from configurable thresholds and multipliers — no baked-in minutes or rates. Approved OT becomes a pay component in payroll.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `overtime.view` | View records |
| `overtime.approve` | Approve/reject |
| `overtime.manage-policies` | Policies & multipliers |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-OT-FR-001 | Implemented | Overtime policies support daily_threshold_minutes, weekly_threshold_minutes, rest_day_applies, public_holiday_applies, effective dating, priority, entity/branch scope. |
| P12-OT-FR-002 | Implemented | Multipliers per day_type: weekday / weekend / rest_day / public_holiday. |
| P12-OT-FR-003 | Implemented | Policy resolution uses most specific active policy (entity/branch/effective-date/priority) — AccountResolver-style. |
| P12-OT-FR-004 | Implemented | Overtime records store regular/overtime minutes, day_type, resolved_multiplier, policy_id, approval status. |
| P12-OT-FR-005 | Implemented | Unapproved overtime is excluded from payroll; approved OT maps to `overtime_expense` pay component. |
| P12-OT-FR-006 | Implemented | Changing multiplier/threshold via config affects next run without deployment. |
| P12-OT-FR-007 | Planned | Integration with shifts_roster for planned shift length as threshold basis. |
| P12-OT-FR-008 | Implemented | `OvertimeEngine::resolveDayType()` already consulted `HolidayResolver::isPublicHoliday()` via `public_holiday_applies` — corrected from a stale "Planned" status found while working on FR-009 below; not itself part of this pass's changes. |
| P12-OT-FR-009 | Implemented | TOIL (comp-off) alternative to paid OT, selected per `overtime_multipliers.compensation_type` (`cash` / `toil` / `employee_choice`). Rest-day detection now actually wires `OvertimePolicy.rest_day_applies` + `EmployeeShiftPreference.rest_days` into `resolveDayType()` (previously present in schema/fillable but never consulted — verified dead prior to this change). For `employee_choice`, the approving manager records the employee's cash/toil decision as part of the single-step approve action (`compensation_choice`); it is persisted on the `OvertimeRecord` at approval time and never re-evaluated if the policy config changes later. See [toil.md](./toil.md) for the full TOIL ledger/claim design. |

---

## 4. Domain model

```text
overtime_policies
- id, legal_entity_id nullable, branch_id nullable,
  daily_threshold_minutes, weekly_threshold_minutes,
  rest_day_applies, public_holiday_applies,
  effective_from / effective_to, status, priority

overtime_multipliers
- id, overtime_policy_id, day_type, multiplier

overtime_records
- id, employee_id, date, regular_minutes, overtime_minutes, day_type,
  resolved_multiplier, overtime_policy_id, approved_by nullable, status
```

---

## 5. Services & interfaces

```text
OvertimeEngine
  resolvePolicy / calculate / submit / approve
```

---

## 6. Domain events

```text
overtime.calculated
overtime.approved
overtime.rejected
```

GL only via payroll component on `payroll.posted`.

---

## 7. Configurability surface

* All thresholds and multipliers — tables only.

---

## 8. Historical migration inputs

* Approved OT history if converting mid-year (optional).

---

## 9. Reports / ESS touchpoints

* OT register; ESS request/view (Planned).

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-OT-AC-001 | Implemented | Unapproved OT excluded from payroll. |
| P12-OT-AC-002 | Implemented | Approved OT calculated at resolved multiplier from policies. |
| P12-OT-AC-003 | Implemented | Config change of multiplier changes next run without code deploy. |
| P12-OT-AC-004 | Implemented | overtime requires hr+attendance gates. |

---

## 11. Out of scope / deferred hooks

* Statutory OT caps by country — expressed as config ceilings, not hardcode.
