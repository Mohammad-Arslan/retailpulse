# Phase 12 — Onboarding & Offboarding

**Gate / registry key:** `onboarding`  
**Wave:** 4  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

---

## 1. Objective

Configurable checklist-driven onboarding and offboarding workflows (tasks, owners, due dates, asset return, access revoke hooks) — not hardcoded step lists.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `onboarding.manage` / `onboarding.execute` | Templates & tasks |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-ONB-FR-001 | Planned | Onboarding templates per designation/grade/entity with ordered tasks. |
| P12-ONB-FR-002 | Planned | Instance created on hire (from ATS or manual employee create). |
| P12-ONB-FR-003 | Planned | Tasks: assignee role/user, due_offset_days, required flag, status. |
| P12-ONB-FR-004 | Planned | Offboarding templates triggered on termination request. |
| P12-ONB-FR-005 | Planned | Offboarding includes asset return, access checklist, final payroll flag, PF settlement reminder. |
| P12-ONB-FR-006 | Planned | Clearance certificate when all required tasks complete. |
| P12-ONB-FR-007 | Planned | Phase 29 workflow optional for multi-step approvals. |
| P12-ONB-FR-008 | Planned | Templates/task history importable. |

---

## 4. Domain model

```text
hr_process_templates
- id, type (onboarding|offboarding), code, name, legal_entity_id nullable,
  eligibility_json, status

hr_process_template_tasks
- id, template_id, sequence, title, assignee_type, due_offset_days, required

hr_process_instances
- id, template_id, employee_id, type, status, started_at, completed_at

hr_process_tasks
- id, instance_id, template_task_id, assignee_user_id nullable,
  due_date, status, completed_at, notes
```

---

## 5. Services & interfaces

```text
OnboardingService
OffboardingService
ClearanceService
```

---

## 6. Domain events

```text
onboarding.started
onboarding.completed
offboarding.started
offboarding.cleared
```

---

## 7. Configurability surface

* Templates and tasks — data; no baked-in checklist.

---

## 8. Historical migration inputs

* Open instances optional.

---

## 9. Reports / ESS touchpoints

* Task dashboards; ESS new-hire tasks.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-ONB-AC-001 | Planned | Hire with matching template auto-creates instance. |
| P12-ONB-AC-002 | Planned | Clearance blocked while required offboarding tasks open. |
| P12-ONB-AC-003 | Planned | Template edit does not alter in-flight instances’ completed tasks. |

---

## 11. Out of scope / deferred hooks

* Automatic AD/email provisioning — webhook stubs Phase 15.
