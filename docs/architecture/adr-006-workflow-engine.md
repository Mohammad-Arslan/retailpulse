# ADR-006: Internal Workflow Engine

Status: Accepted

Date: 2026-07-19

Related: [ADR-003 Domain Events](./adr-003-domain-events.md) · [ADR-007 Integration Hub](./adr-007-integration-hub.md) · [Phase 29 — Workflow & Approval Engine](../phases/phase-29-workflow-engine.md)

---

# Context

RetailPulse currently gates several sensitive actions behind hard-coded, single-step approval logic: a manager PIN for discounts over a threshold (POS), fixed threshold checks for refunds and purchase orders. These are inflexible — every business has different approval hierarchies, SLAs, and escalation rules, and hard-coding "one manager PIN, one threshold" per feature means every new approvable action requires new bespoke code. The SRS (§3.30) and Phase 29 call for a configurable, multi-step approval engine that businesses can adapt without a code change, covering refunds, purchase orders, discounts, payroll, expenses, and leave.

# Decision

RetailPulse will build and own an **internal Workflow & Approval Engine** (Phase 29) rather than depending on an external workflow/automation tool for this capability.

## Why internal, and explicitly not n8n

The workflow engine described here is a **first-class RetailPulse feature**, not an integration:

- It must enforce **RBAC/policy checks** on who may act at each step ([ADR-010](./adr-010-security-principles.md)) — an external automation tool has no native concept of RetailPulse's roles/branches/tenant boundaries.
- It must participate in the **same database transaction and audit trail** as the entity it gates ([ADR-005](./adr-005-audit-trail.md)) — a PO approval step is itself an auditable, tenant-scoped business record (`workflow_instances`, `workflow_step_logs`), not an external system's log.
- It must work **with zero external dependencies configured** — every business gets approval workflows out of the box; n8n or any external orchestrator is optional and layered on top for businesses that want to *extend* RetailPulse's own events into their broader tooling (see [ADR-007](./adr-007-integration-hub.md)), not a requirement to get approvals working at all.
- Latency and reliability: an approval gate blocking a POS checkout cannot depend on a network call to a third-party automation service being up.

**The dividing line going forward:** if a process step is "who inside this business must approve this business record before it proceeds," it belongs in the Workflow Engine. If a process step is "notify or synchronize with a system outside this business" (a Shopify order pull, a WhatsApp message, a Zapier-style automation a customer wires up themselves), it belongs in the Integration Hub ([ADR-007](./adr-007-integration-hub.md)), which may itself be *triggered* by a workflow engine event, but is not the engine itself.

## Shape of the engine (per Phase 29)

- `workflow_definitions` — declarative: trigger event slug (see [ADR-003](./adr-003-domain-events.md) event-slug convention), JSON conditions, ordered JSON steps (assignee role, SLA hours, timeout action).
- `workflow_instances` — one running approval per triggering entity (`entity_type` + `entity_id`), current step, status.
- `workflow_step_logs` — immutable, append-only per-step outcome log (approved/rejected/escalated/timeout), consistent with [ADR-005](./adr-005-audit-trail.md)'s "append, don't overwrite" principle for business-history records.
- `WorkflowEngine::initiate()` / `::act()` are the only sanctioned entry points for advancing an instance — callers do not mutate `workflow_instances` rows directly.
- Pre-built definitions ship seeded but **inactive by default**; a business opts in per definition in Settings → Workflows, consistent with RetailPulse's "configuration over hardcoding" principle ([ADR-011](./adr-011-development-standards.md)).

## Backwards compatibility with existing PIN gates

Existing hard-coded PIN approval flows (POS discount override, etc.) are not ripped out when Phase 29 lands — they remain the fallback when a business has not activated the corresponding workflow definition, gated by a feature flag per call site (see the Phase 29 doc's example in `docs/phases/phase-29-workflow-engine.md`). New approval-gated features built **after** Phase 29 ships should be built against the Workflow Engine directly rather than adding another bespoke PIN gate — the PIN pattern is legacy-compatibility, not the template for new work.

## Escalation and SLA

Timeout/escalation is handled by a scheduled job (`ProcessWorkflowTimeoutsJob`) evaluating SLA hours per step, not by ad hoc per-feature cron jobs — one scheduler, one escalation code path, consistent outcomes (`escalated`, `auto_approve`, `reject` on timeout) across every workflow definition.

# Consequences

- Businesses configure approval chains (who, how many steps, what SLA) without a deploy — this is a data change (`workflow_definitions` rows), not a code change.
- Every approval decision, everywhere in the app, produces the same shape of auditable history (`workflow_step_logs`), instead of each module inventing its own approval logging.
- Until Phase 29 ships, do not build a second bespoke multi-step approval mechanism for a new feature that needs one — either extend the existing PIN-gate pattern minimally and flag it as workflow-engine debt in the relevant phase gap doc, or pull Phase 29 scope forward deliberately; don't invent a third approval mechanism in the interim.
