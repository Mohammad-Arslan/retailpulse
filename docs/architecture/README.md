# RetailPulse Architecture Documentation

This directory is the **Architecture Bible** — the authoritative source of truth for how RetailPulse is built. It exists because RetailPulse is not a single-purpose POS script; it is being built as a long-term, multi-tenant, enterprise SaaS ERP intended to compete with the likes of NetSuite, Dynamics 365, and Odoo over a 15+ year horizon ([`docs/vision.md`](../vision.md)), and decisions made early (data isolation, module boundaries, event conventions, audit strategy) are expensive to reverse once hundreds of tables and dozens of modules depend on them.

## Where this fits in RetailPulse's documentation

```
docs/README.md            ← start here: what RetailPulse is, how the docs fit together
    ↓
docs/vision.md             ← WHY RetailPulse exists, what it's becoming
    ↓
docs/principles.md         ← the non-negotiable engineering principles behind every ADR
    ↓
docs/architecture/README.md (this file) ← WHAT and WHY, per architectural area
    ↓
docs/architecture/adr-NNN-*.md          ← the specific, binding decisions
    ↓
.ai/rules/*.mdc             ← HOW to write the code inside those decisions
    ↓                         (Cursor loads via .cursor/rules → .ai/rules)
Implementation (app/, resources/, database/, tests/)
```

Read in this order the first time. Once oriented, jump directly to the ADR(s) relevant to the change at hand.

## Rules for every contributor — human or AI

1. **Every new feature must comply with the ADRs in this directory.** Before adding a table, a service, an event, an API endpoint, or a frontend pattern, check whether an ADR already governs it.
2. **These decisions are authoritative, not advisory.** They are not a style guide you can quietly deviate from because a shortcut looks faster in the moment. Deviating from an ADR is itself an architectural change and must be proposed as one (see "Changing a decision" below).
3. **AI coding agents (Claude Code, Cursor, Copilot, etc.) must read the relevant ADRs before making architectural changes.** "Architectural" means: new tables, new modules or module boundaries, new cross-cutting concerns (events, jobs, middleware), new API surfaces, new frontend state-management patterns, or anything touching tenancy, security, or the layered request pipeline. Trivial bug fixes and copy changes do not require reading this directory first, but if a fix reveals the code violates an ADR, flag it rather than silently deepening the violation. See the **AI Governance** section below and `CLAUDE.md` for the full onboarding sequence.
4. **If the current implementation conflicts with an ADR, the ADR wins** — unless the ADR has been formally superseded (see "Changing a decision" below). Treat the gap as a bug to schedule, not a precedent to extend. `docs/gaps/gaps.md` and phase gap docs (e.g. `docs/phases/phase-12/gaps.md`) are where such known deviations are tracked until resolved.
5. **Every ADR describes both the current state and the target state.** RetailPulse ships incrementally (see `docs/roadmap-philosophy.md` and `docs/phases/`). Several ADRs here — multi-tenancy, workflow engine, plugin system, deployment — document infrastructure that is intentionally not built yet. Read each ADR's "How" section (which states current state explicitly where it matters) before assuming a described mechanism (e.g. `TenantScope`, `WorkflowEngine`) already exists in the codebase — grep for it. Do not implement speculative infrastructure ahead of its scheduled phase without a written decision to pull it forward.

## Every ADR follows the same shape

So any ADR can be read the same way regardless of topic:

- **Why** — the problem or context that made a decision necessary.
- **What** — the decision itself, stated plainly.
- **How** — the concrete implementation: current state, target state, and the rules that follow from the decision.
- **Trade-offs** — what is deliberately given up in exchange for the decision's benefits.
- **Alternatives Considered** — what else was evaluated, and why it wasn't chosen.
- **Future Direction** — how the decision is expected to evolve.
- **Impact on Future Development** — the concrete, checkable consequences for anyone building on top of it.

## Index

| ADR | Title | Governs |
| :--- | :--- | :--- |
| [ADR-001](./adr-001-saas-multi-tenancy.md) | SaaS Multi-Tenancy Strategy | Tenant data model, isolation strategy, phased rollout, tenant classification, tenant lifecycle — schema prep inventory: [tenant-schema-preparation.md](./tenant-schema-preparation.md) |
| [ADR-002](./adr-002-modular-monolith.md) | Modular Monolith Architecture | Module boundaries, ownership, cross-module communication |
| [ADR-003](./adr-003-backend-architecture.md) | Backend Architecture | Controller → FormRequest → Service → Repository → DTO → Model, DI, transactions, error handling |
| [ADR-004](./adr-004-frontend-architecture.md) | Frontend Architecture (React + Inertia) | Page structure, Inertia standards, component hierarchy, design system, forms, tables, i18n |
| [ADR-005](./adr-005-domain-events.md) | Domain Events | Event-driven integration points, naming conventions, publishing/subscribing, future event bus |
| [ADR-006](./adr-006-workflow-engine.md) | Internal Workflow Engine | Approval chains, SLA/escalation, versioning, why this is not n8n |
| [ADR-007](./adr-007-integration-hub.md) | Integration Hub | Webhooks, external connectors (Shopify/WooCommerce/TikTok/WhatsApp/accounting/Power BI), n8n strategy |
| [ADR-008](./adr-008-public-api.md) | Public REST API Strategy | API-first philosophy, versioning, authentication, SDKs, developer portal, future GraphQL |
| [ADR-009](./adr-009-plugin-system.md) | Plugin System | Extension points, discovery, registration, marketplace/licensing future scope |
| [ADR-010](./adr-010-security.md) | Security | RBAC, policies, tenant isolation, encryption, secrets, rate limiting, OWASP, compliance readiness |
| [ADR-011](./adr-011-audit-history.md) | Audit History & Immutable Records | Audit logging, soft deletes, reversal strategy, historical preservation |
| [ADR-012](./adr-012-development-standards.md) | Development Standards | Coding standards, refactoring policy, naming, directory structure, review process |
| [ADR-013](./adr-013-testing-strategy.md) | Testing Strategy | Unit/Feature/Integration/Browser/Performance/Security tests, coverage expectations |
| [ADR-014](./adr-014-performance.md) | Performance | Caching, queues, lazy loading, indexes, N+1 prevention, scaling |
| [ADR-015](./adr-015-database-standards.md) | Database Standards | Naming, migrations, indexes, FKs, nullable policy, `tenant_id` policy, soft deletes |
| [ADR-016](./adr-016-reporting-bi.md) | Reporting & Business Intelligence | Operational vs. analytical reporting, data mart, Power BI/Tableau, predictive analytics |
| [ADR-017](./adr-017-ai-architecture.md) | AI Architecture | AI assistants, prompt strategy, AI permissions/safety, extensibility, future Copilot |
| [ADR-018](./adr-018-deployment.md) | Deployment | Environments, shared vs. dedicated topology, CI/CD, DR/RTO/RPO |
| [ADR-019](./adr-019-shared-file-storage.md) | Shared File Storage | One admin-configurable storage backend for images/attachments/import-export, per-row disk permanence, Octane `flush` rule |

[**Glossary**](./glossary.md) — definitions for terminology used consistently across the SRS, phase docs, ADRs, and code.

## Relationship to other documentation

- **[`docs/vision.md`](../vision.md) and [`docs/principles.md`](../principles.md)** — the *why* and the non-negotiable engineering principles behind every ADR in this directory. Read before the ADRs if you haven't already.
- **[`docs/roadmap-philosophy.md`](../roadmap-philosophy.md)** — how the phase roadmap is designed, how architectural decisions get introduced, how technical debt is managed.
- **`docs/srs.md`** — the Software Requirements Specification. It defines *what* the system must do, per module, per phase. These ADRs define *how* the system is built to satisfy the SRS durably.
- **`docs/phases/`** — the phase-by-phase delivery roadmap. Phase docs describe planned schema and services for a specific slice of scope (e.g. Phase 28 for tenancy, Phase 29 for workflow). The ADRs here are the cross-phase architectural contract those phases must honor.
- **[tenant-schema-preparation.md](./tenant-schema-preparation.md)** — inventory of tables that already have / received nullable `tenant_id` ahead of Phase 28 enforcement (companion to ADR-001).
- **`docs/implementation-status.md`** — a point-in-time snapshot of what's actually built. It changes phase to phase; the ADRs do not.
- **[`.ai/rules/*.mdc`](../../.ai/README.md)** — implementation-level coding standards (file placement, exact patterns, code-level checklists). These rules implement the ADRs; they state *how*, this directory states *why* and *what*. A rule should reference the ADR(s) it implements (`Implements: ADR-003`) rather than re-explaining the architecture. Cursor resolves the same files through `.cursor/rules` (symlink/junction to `.ai/rules`).
- **`CLAUDE.md` / `AGENTS.md`** (repo root) — AI onboarding: read order and how to reconcile architecture with implementation. They do not duplicate ADRs or rules — they tell an agent how to use both.

## AI Governance

Every AI coding agent working on RetailPulse — regardless of tool (Claude Code, Cursor, Copilot, or any other) — is expected to:

1. **Read documentation before coding** — in the order shown above, at minimum the ADR(s) relevant to the task.
2. **Follow architecture before implementation** — when the two conflict, the ADR wins (see "Changing a decision" below), not the path of least resistance in the existing code.
3. **Respect existing patterns** — match the nearest existing analog rather than introducing a novel structure for a problem this codebase already has a convention for.
4. **Never invent competing architectures** — no second state-management library, no parallel service-layer pattern, no second audit mechanism. One way to do a thing, per ADR.
5. **Never duplicate existing systems** — reuse services, repositories, DTOs, and components ([ADR-012](./adr-012-development-standards.md)) rather than writing a parallel implementation because the existing one was inconvenient to find or reuse.
6. **Keep modules isolated** — respect the boundaries and communication rules in [ADR-002](./adr-002-modular-monolith.md).
7. **Preserve backward compatibility** — per [ADR-008](./adr-008-public-api.md) (API contracts) and [ADR-015](./adr-015-database-standards.md) (expand-first migrations) — whenever a change can be made compatibly, it is.
8. **When architecture and implementation differ**: explain the inconsistency (flag it, note it in the relevant gap doc if appropriate) rather than silently "fixing" the code to match the agent's own assumption of what's correct, or silently deepening the deviation.
9. **When proposing an architectural improvement**: justify the change, state the trade-offs, and update the relevant ADR if the decision actually changes — an improvement that isn't reflected back into the ADR is not actually adopted, it's a one-off deviation waiting to confuse the next contributor.

`CLAUDE.md` and `AGENTS.md` are the concrete, operational expansion of this section — the step-by-step onboarding sequence an AI agent follows before writing code in this repository.

## Changing a decision

An ADR is not immutable, but it is not casually overridden either. To change one:

1. Write the proposed change as a diff to the existing ADR (or a new ADR that explicitly supersedes it).
2. State what breaks or must be migrated as a result, and address the Trade-offs/Alternatives sections — a change that doesn't reckon with why the original decision was made isn't a considered change.
3. Get it reviewed and merged like any other architectural change — not silently reinterpreted in a PR that does something else.

Once merged, update the "Status" field of the superseded ADR (`Superseded by ADR-0XX`) rather than deleting it — the history of *why* a decision changed is part of the value of this directory.
