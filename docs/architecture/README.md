# RetailPulse Architecture Documentation

This directory is the **authoritative source of truth** for RetailPulse's system architecture. It exists because RetailPulse is not a single-purpose POS script — it is being built as a long-term, multi-tenant, enterprise ERP platform (per `docs/srs.md` v4.0 and the phase roadmap in `docs/phases/`), and decisions made early (data isolation, module boundaries, event conventions, audit strategy) are expensive to reverse once hundreds of tables and dozens of modules depend on them.

## Rules for every contributor — human or AI

1. **Every new feature must comply with the ADRs in this directory.** Before adding a table, a service, an event, an API endpoint, or a frontend pattern, check whether an ADR already governs it.
2. **These decisions are authoritative, not advisory.** They are not a style guide you can quietly deviate from because a shortcut looks faster in the moment. Deviating from an ADR is itself an architectural change and must be proposed as one (see below).
3. **AI coding agents (Claude Code, Cursor, Copilot, etc.) must read the relevant ADRs before making architectural changes.** "Architectural" means: new tables, new modules or module boundaries, new cross-cutting concerns (events, jobs, middleware), new API surfaces, new frontend state-management patterns, or anything touching tenancy, security, or the layered request pipeline. Trivial bug fixes and copy changes do not require reading this directory first, but if a fix reveals the code violates an ADR, flag it rather than silently deepening the violation.
4. **If the current implementation conflicts with an ADR, the ADR wins** — unless the ADR has been formally superseded (see "Changing a decision" below). Treat the gap as a bug to schedule, not a precedent to extend. `docs/gaps/gaps.md` and phase gap docs (e.g. `docs/phases/phase-12/gaps.md`) are where such known deviations are tracked until resolved.
5. **Every ADR describes both the current state and the target state.** RetailPulse ships incrementally (see the phase roadmap). Several ADRs here — multi-tenancy, workflow engine, plugin architecture — document infrastructure that is intentionally not built yet. Read the "Current State" section of each ADR before assuming a described mechanism (e.g. `TenantScope`, `WorkflowEngine`) already exists in the codebase; grep for it. Do not implement speculative infrastructure ahead of its scheduled phase without a written decision to pull it forward.

## Index

| ADR | Title | Governs |
| :--- | :--- | :--- |
| [ADR-001](./adr-001-saas-multi-tenancy.md) | SaaS Multi-Tenancy Strategy | Tenant data model, isolation strategy, phased rollout, tenant classification rules |
| [ADR-002](./adr-002-modular-architecture.md) | Modular Monolith Architecture | Module boundaries, ownership, cross-module communication |
| [ADR-003](./adr-003-domain-events.md) | Domain Events | Event-driven integration points, naming conventions, when to use events vs. direct calls |
| [ADR-004](./adr-004-layered-architecture.md) | Layered Backend Architecture | Controller → FormRequest → Service → Repository → DTO → Model, responsibility boundaries |
| [ADR-005](./adr-005-audit-trail.md) | Audit Trail & Immutable History | Audit logging, soft deletes, reversal strategy, financial record immutability |
| [ADR-006](./adr-006-workflow-engine.md) | Internal Workflow Engine | Approval chains, SLA/escalation, why this is not n8n |
| [ADR-007](./adr-007-integration-hub.md) | Integration Hub | Webhooks, external connectors, n8n strategy, e-commerce/WhatsApp/accounting integrations |
| [ADR-008](./adr-008-public-api.md) | Public REST API Strategy | API-first philosophy, versioning, authentication, external developer support |
| [ADR-009](./adr-009-plugin-architecture.md) | Plugin Architecture | Extension points, module registration, marketplace vision |
| [ADR-010](./adr-010-security-principles.md) | Security Principles | RBAC, policies, tenant isolation, encryption, secrets, rate limiting, secure defaults |
| [ADR-011](./adr-011-development-standards.md) | Development Standards | Coding standards, configuration over hardcoding, service/repository usage, testing, documentation |
| [ADR-012](./adr-012-frontend-architecture.md) | Frontend Architecture (React + Inertia) | Page structure, Inertia standards, component hierarchy, design system, forms, tables, i18n |

## Relationship to other documentation

- **`docs/srs.md`** — the Software Requirements Specification. It defines *what* the system must do, per module, per phase. These ADRs define *how* the system is built to satisfy the SRS durably.
- **`docs/phases/`** — the phase-by-phase delivery roadmap. Phase docs describe planned schema and services for a specific slice of scope (e.g. Phase 28 for tenancy, Phase 29 for workflow). The ADRs here are the cross-phase architectural contract those phases must honor.
- **`docs/implementation-status.md`** — a point-in-time snapshot of what's actually built. It changes phase to phase; the ADRs do not.
- **`CLAUDE.md`** (repo root) — day-to-day command reference and a condensed architecture summary for coding agents. It defers to this directory for the authoritative version of anything it summarizes.

## Changing a decision

An ADR is not immutable, but it is not casually overridden either. To change one:

1. Write the proposed change as a diff to the existing ADR (or a new ADR that explicitly supersedes it).
2. State what breaks or must be migrated as a result.
3. Get it reviewed and merged like any other architectural change — not silently reinterpreted in a PR that does something else.

Once merged, update the "Status" field of the superseded ADR (`Superseded by ADR-0XX`) rather than deleting it — the history of *why* a decision changed is part of the value of this directory.
