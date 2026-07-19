# ADR-006: Internal Workflow Engine

Status: Accepted

Date: 2026-07-19

Related: [ADR-005 Domain Events](./adr-005-domain-events.md) · [ADR-007 Integration Hub](./adr-007-integration-hub.md) · [ADR-010 Security](./adr-010-security.md) · [Phase 29 — Workflow & Approval Engine](../phases/phase-29-workflow-engine.md)

---

## Why

RetailPulse currently gates several sensitive actions behind hard-coded, single-step approval logic: a manager PIN for discounts over a threshold (POS), fixed threshold checks for refunds and purchase orders. These are inflexible — every business has different approval hierarchies, SLAs, and escalation rules, and hard-coding "one manager PIN, one threshold" per feature means every new approvable action requires new bespoke code. The SRS (§3.30) and Phase 29 call for a configurable, multi-step approval engine that businesses can adapt without a code change, covering refunds, purchase orders, discounts, payroll, expenses, and leave.

## What

RetailPulse builds and owns an **internal Workflow & Approval Engine** (Phase 29) rather than depending on an external workflow/automation tool for this capability.

## How

### Why internal, and explicitly not n8n

The workflow engine described here is a **first-class RetailPulse feature**, not an integration:

- It must enforce **RBAC/policy checks** on who may act at each step ([ADR-010](./adr-010-security.md)) — an external automation tool has no native concept of RetailPulse's roles/branches/tenant boundaries.
- It must participate in the **same database transaction and audit trail** as the entity it gates ([ADR-011](./adr-011-audit-history.md)) — a PO approval step is itself an auditable, tenant-scoped business record (`workflow_instances`, `workflow_step_logs`), not an external system's log.
- It must work **with zero external dependencies configured** — every business gets approval workflows out of the box; n8n or any external orchestrator is optional and layered on top for businesses that want to *extend* RetailPulse's own events into their broader tooling (see [ADR-007](./adr-007-integration-hub.md)), not a requirement to get approvals working at all.
- Latency and reliability: an approval gate blocking a POS checkout cannot depend on a network call to a third-party automation service being up.

**The dividing line going forward:** if a process step is "who inside this business must approve this business record before it proceeds," it belongs in the Workflow Engine. If a process step is "notify or synchronize with a system outside this business" (a Shopify order pull, a WhatsApp message, a Zapier-style automation a customer wires up themselves), it belongs in the Integration Hub ([ADR-007](./adr-007-integration-hub.md)), which may itself be *triggered* by a workflow engine event, but is not the engine itself.

### Shape of the engine (per Phase 29)

- `workflow_definitions` — declarative: trigger event slug (see [ADR-005](./adr-005-domain-events.md) event-slug convention), JSON conditions, ordered JSON steps (assignee role, SLA hours, timeout action).
- `workflow_instances` — one running approval per triggering entity (`entity_type` + `entity_id`), current step, status.
- `workflow_step_logs` — immutable, append-only per-step outcome log (approved/rejected/escalated/timeout), consistent with [ADR-011](./adr-011-audit-history.md)'s "append, don't overwrite" principle for business-history records.
- `WorkflowEngine::initiate()` / `::act()` are the only sanctioned entry points for advancing an instance — callers do not mutate `workflow_instances` rows directly.
- Pre-built definitions ship seeded but **inactive by default**; a business opts in per definition in Settings → Workflows, consistent with RetailPulse's "configuration over hardcoding" principle ([ADR-012](./adr-012-development-standards.md)).

### Versioning of workflow definitions

A `workflow_definitions` row is business configuration, not code — but it still needs a versioning discipline, because a running `workflow_instance` must not have its rules rewritten out from under it mid-flight:

- Editing an **inactive** definition (never yet triggered, or currently disabled) is a direct update — no versioning concern, nothing depends on it yet.
- Editing an **active** definition with running instances creates a new definition version rather than mutating the live one in place; running instances keep executing against the version they were initiated under, and new instances triggered after the edit pick up the new version. This mirrors [ADR-011](./adr-011-audit-history.md)'s "correction is a new record, not an edit of history" principle applied to configuration instead of transactions.
- The visual workflow builder (Settings → Workflows) surfaces version history per definition (who changed what step, when) so a business can audit why an approval behaved differently before and after a given date — this is itself an audited change per [ADR-011](./adr-011-audit-history.md).
- A definition's trigger event slug is part of its version-stable identity — changing which event triggers a definition is modeled as retiring the old definition and activating a new one, not silently repointing an existing definition's trigger.

### Backwards compatibility with existing PIN gates

Existing hard-coded PIN approval flows (POS discount override, etc.) are not ripped out when Phase 29 lands — they remain the fallback when a business has not activated the corresponding workflow definition, gated by a feature flag per call site (see the Phase 29 doc's example in `docs/phases/phase-29-workflow-engine.md`). New approval-gated features built **after** Phase 29 ships should be built against the Workflow Engine directly rather than adding another bespoke PIN gate — the PIN pattern is legacy-compatibility, not the template for new work.

### Escalation and SLA

Timeout/escalation is handled by a scheduled job (`ProcessWorkflowTimeoutsJob`) evaluating SLA hours per step, not by ad hoc per-feature cron jobs — one scheduler, one escalation code path, consistent outcomes (`escalated`, `auto_approve`, `reject` on timeout) across every workflow definition.

### Internal ownership

`WorkflowEngine`, `WorkflowDefinition`/`WorkflowInstance`/`WorkflowStepLog` models, and `ProcessWorkflowTimeoutsJob` are core RetailPulse code, owned the same way any other domain module is owned ([ADR-002](./adr-002-modular-monolith.md)) — there is no external service, vendor SDK, or automation platform in this engine's critical path. A business's approval chain works identically whether or not that business has ever configured an external integration.

## Trade-offs

- **Building and maintaining an approval engine is more work than adopting an existing workflow product (Camunda, Temporal, or a SaaS approval tool)** — accepted because none of those integrate natively with RetailPulse's RBAC, branch/tenant scoping, and audit trail without becoming a second source of truth for "who can approve what," which is a worse long-term cost than the build.
- **JSON-defined conditions/steps are less expressive than a general-purpose scripting/DSL engine** — accepted deliberately: the visual builder's target users are business owners configuring approval chains, not developers writing workflow scripts. If a genuinely complex conditional need arises that JSON conditions can't express, it is evaluated as a scoped extension to the conditions schema, not a reason to embed a scripting language.
- **One engine for a wide variety of approval shapes** (refunds, POs, payroll, leave) means the schema must stay generic enough to cover all of them — a highly domain-specific approval quirk may need to live as a pre/post hook around `WorkflowEngine::act()` rather than being expressible purely in `steps_json`.

## Alternatives considered

- **Per-feature approval logic, improved but still bespoke** (i.e., just make the existing PIN-gate pattern configurable per feature) — rejected: it solves configurability for one feature at a time but never gives a business a single place to see "everything pending my approval," which is an explicit SRS §3.30 requirement, and it would mean re-solving SLA/escalation logic independently per feature.
- **Adopting n8n (or a similar automation tool) as the approval engine itself** — rejected per "Why internal, and explicitly not n8n" above; the reasoning generalizes to any external workflow SaaS, not n8n specifically.
- **A general-purpose BPMN engine (e.g. embedding a BPMN interpreter)** — rejected as over-engineering for RetailPulse's actual approval shapes, which are linear-with-escalation, not the arbitrary parallel/gateway flows BPMN is built for; adopting BPMN would mean paying its authoring complexity for flexibility the product doesn't need.

## Future direction

As new modules ship, each identifies its approval-gated actions and defines them against this engine (post-Phase-29) rather than inventing feature-specific gates. The visual builder is expected to remain the primary authoring surface — direct SQL/seeder edits to `workflow_definitions` are a bootstrapping/seeding mechanism (pre-built definitions), not the intended ongoing editing path for a live business.

## Impact on future development

- Businesses configure approval chains (who, how many steps, what SLA) without a deploy — this is a data change (`workflow_definitions` rows), not a code change.
- Every approval decision, everywhere in the app, produces the same shape of auditable history (`workflow_step_logs`), instead of each module inventing its own approval logging.
- Until Phase 29 ships, do not build a second bespoke multi-step approval mechanism for a new feature that needs one — either extend the existing PIN-gate pattern minimally and flag it as workflow-engine debt in the relevant phase gap doc, or pull Phase 29 scope forward deliberately; don't invent a third approval mechanism in the interim.
