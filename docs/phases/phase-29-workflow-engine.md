# Phase 29 — Workflow & Approval Engine

**SRS Reference:** §3.30 (Workflow Engine)
**Status:** Planned
**Depends on:** Phase 12 (Expenses & HR — payroll approval hook), Phase 23 (Module Config Engine — workflow module gate)
**Feeds into:** All phases that have approval hooks (10, 12, 14, 5 reorder, 9 credit override)

---

## Objective
Replace all hard-coded PIN-based approval gates with a configurable, multi-step workflow engine. A workflow definition describes the steps, assignees, and conditions; a workflow instance tracks a specific approval in progress. Pre-built workflows cover refunds, purchase orders, discounts, and payroll — and the visual builder lets businesses create custom workflows without code.

---

## 1. Data Model

### workflow_definitions
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| name | varchar(150) | "Refund Approval", "Large PO Approval" |
| slug | varchar(120) unique | `refund.approval`, `po.approval` |
| trigger_event | varchar(150) | Event slug e.g. `po.created`, `return.initiated` |
| conditions_json | json nullable | e.g. `{"amount": {">": 50000}, "branch_id": 1}` |
| steps_json | json | Ordered list of step actions |
| is_active | boolean | |
| created_at / updated_at | timestamps | |

Step definition JSON structure (within `steps_json`):
```json
{
  "step_name": "manager_review",
  "assignee_type": "role",
  "assignee_value": "branch_manager",
  "timeout_hours": 24,
  "on_timeout": "escalate",
  "escalate_to_role": "owner"
}
```

### workflow_instances
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| definition_id | bigint FK | |
| entity_type | varchar(150) | `App\Models\Sale`, `App\Models\PurchaseOrder` |
| entity_id | bigint | |
| current_step | integer | 0-based step index |
| status | enum | `running`, `completed`, `escalated`, `cancelled` |
| initiated_by | bigint FK → users | |
| started_at | timestamp | |
| completed_at | timestamp nullable | |
| metadata | json nullable | Snapshot of entity data at initiation |

### workflow_step_logs (completed steps log — per §3.30)
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| instance_id | bigint FK | |
| step_index | integer | |
| action_type | varchar(80) | `send_notification`, `require_approval`, `update_field`, `create_record`, `webhook` |
| actioned_by | bigint FK → users nullable | Null if timeout/system |
| actioned_at | timestamp nullable | |
| outcome | varchar(50) | `approved`, `rejected`, `escalated`, `timeout` |
| notes | text nullable | |

---

## 2. Workflow Engine Service

`WorkflowEngine::initiate(string $definitionSlug, Model $entity, User $initiatedBy): WorkflowInstance`

1. Loads the definition by slug.
2. Evaluates `conditions_json` against the entity (skip if conditions not met → PIN fallback or auto-approve).
3. Creates `WorkflowInstance` with `status = running`, `current_step = 0`.
4. Dispatches `WorkflowStepAssigned` event → notification to the assignee(s).

`WorkflowEngine::act(WorkflowInstance $instance, User $actor, string $action, ?string $notes): void`

1. Validates actor has the required role for the current step.
2. Records `workflow_step_logs` entry.
3. If `approve` and more steps remain: advance `current_step`, notify next assignee.
4. If `approve` and last step: mark instance `approved`; dispatch `WorkflowInstanceApproved` event → caller hook.
5. If `reject`: mark instance `rejected`; dispatch `WorkflowInstanceRejected` event → caller hook.
6. If `escalate`: find next `escalate_to_role` from step config; notify escalation target.

---

## 3. Pre-Built Workflow Definitions (Seeder)

`WorkflowDefinitionSeeder` seeds these inactive-by-default definitions:

| Slug | Trigger | Condition | Steps |
| :--- | :--- | :--- | :--- |
| `refund.approval` | `RefundInitiated` | `amount > settings.returns.approval_threshold` | 1: Branch Manager → approve/reject |
| `po.approval` | `PurchaseOrderCreated` / submitted PO | `total > settings.procurement.approval_threshold` | 1: Branch Manager; 2 (if > higher threshold): Owner |
| `pr.approval` | `PurchaseRequestSubmitted` | `total > settings.procurement.pr_approval_threshold` | 1: Branch Manager; 2 (if > higher threshold): Owner |
| `discount.approval` | `DiscountApplied` | `discount_pct > settings.pos.max_discount_pct` | 1: Branch Manager |
| `payroll.approval` | `PayrollRunCreated` | Always | 1: Owner or Accountant |
| `expense.approval` | `expense.submitted` | `amount > settings.expenses.approval_threshold` | 1: Branch Manager |
| `leave.approval` | `leave_request.submitted` | Always | 1: Line Manager → 2: HR |
| `supplier_invoice.match` | `supplier_invoice.pending_match` | `match_status != fully_matched` | 1: Procurement Officer |
| `reorder.approval` | `stock.below_reorder` | Auto-draft PO created | 1: Branch Manager |

Businesses activate the definitions they need in Settings → Workflows.

---

## 4. Backwards Compatibility with PIN Gates

Existing PIN approval flows (Phase 8, 10, 14) are extended:

```php
if (FeatureFlagService::isEnabled('workflow.refund_approval')) {
    WorkflowEngine::initiate('refund.approval', $sale, $cashier);
    return response()->json(['status' => 'pending_approval', 'instance_id' => $instance->id]);
} else {
    // Original PIN gate
    if (!PosPinService::verify($managerPin, $manager)) abort(403);
}
```

When the workflow feature flag is off, the original PIN behaviour is unchanged.

---

## 5. Timeout & Escalation

`ProcessWorkflowTimeoutsJob` — runs every 15 minutes.
- Finds instances where current step SLA (hours) has elapsed without action.
- If `on_timeout = escalate`: calls `WorkflowEngine::act($instance, null, 'escalate')`.
- If `on_timeout = auto_approve`: calls `WorkflowEngine::act($instance, null, 'approve')`.
- If `on_timeout = reject`: calls `WorkflowEngine::act($instance, null, 'reject')`.

---

## 6. Visual Workflow Builder (Admin UI)

Admin → Settings → Workflows:

- **Definitions list:** Table of all definitions; toggle active/inactive.
- **Edit Definition:** Drag-and-drop workflow builder — trigger → conditions → steps → actions; configure assignee role, SLA hours, and timeout action per step.
- **Active Instances / Pending Approvals Dashboard:** Live list across all workflows; SLA violations highlighted; drill into step history and act (approve/reject).
- **History:** Completed workflow instances with outcome and timing.

---

## 7. Notifications

`WorkflowStepAssigned` event → `SendWorkflowAssignmentNotification`:
- In-app notification with action buttons (Approve / Reject) visible in the notification centre.
- Email notification with a deep-link to the Admin → Active Instances page.
- Mobile push notification if the Manager App is installed (Phase 26).

---

## 8. API Endpoints

| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| GET | /api/v1/workflows/definitions | workflows.view | List definitions |
| PUT | /api/v1/workflows/definitions/{id} | workflows.manage | Update definition |
| GET | /api/v1/workflows/instances | workflows.view | Active instances (manager view) |
| GET | /api/v1/workflows/instances/{id} | workflows.view | Instance detail |
| POST | /api/v1/workflows/instances/{id}/act | workflows.act | Approve/reject/escalate |
| GET | /api/v1/mobile/manager/approvals | mobile:manager | Pending approvals for manager app |

---

## 9. Services & Classes

- `WorkflowEngine` — initiate, act, advance, complete.
- `WorkflowDefinition`, `WorkflowInstance`, `WorkflowStepLog` models.
- `ProcessWorkflowTimeoutsJob` — escalation/timeout processor (every 15 min).
- `WorkflowDefinitionSeeder` — seeds pre-built definitions.
- `WorkflowStepAssigned` event / `SendWorkflowAssignmentNotification`.
- `WorkflowInstanceApproved`, `WorkflowInstanceRejected` events — consumed by caller hooks in refund, PO, discount, payroll flows.

---

## SRS v4.0 Enhancements (§3.30)

Aligns with full Workflow Engine specification added in SRS v4.0 (previously tables-only in v3.0).

### Step Action Types

- `send_notification` — to role or specific user
- `require_approval` — blocks until approved/rejected
- `update_field` — e.g. set `status = approved`
- `create_record` — e.g. auto-create draft PO from reorder alert
- `webhook` — POST to external URL

### SLA / Escalation

- Each approval step has configurable SLA in hours; escalations logged in `workflow_step_logs` and visible on pending approvals dashboard.
- Reverb channel `workflow.approval_required.{userId}` on step assignment (Phase 6).

### Acceptance Criteria (v4.0)

1. PO over threshold triggers `po.approval` workflow when feature flag enabled; PIN fallback when disabled.
2. Purchase Request over threshold triggers `pr.approval` when `feature_flags.procurement.pr_workflow_approval` enabled; PIN fallback via `PinPrApprovalStrategy` when disabled (`WorkflowPrApprovalStrategy` stubs until this phase).
3. Blind close requires manager PIN when flag off; workflow when on.
4. SLA breach escalates to configured role and logs `outcome = escalated`.
5. Drag-and-drop builder persists valid `steps_json` and activates definition.
6. `supplier_invoice.pending_match` workflow routes unmatched invoice to Procurement Officer.
