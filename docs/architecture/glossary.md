# RetailPulse Glossary

Terminology used consistently across the SRS, phase docs, ADRs, and code. When a term has a precise architectural meaning (not just its everyday English sense), that meaning is defined here — code, docs, and conversation should all use it the same way.

---

**ADR (Architecture Decision Record)** — a document in `docs/architecture/` recording a specific, binding architectural decision: why it was made, what was decided, how it's implemented, its trade-offs, alternatives considered, and its future direction. See [README.md](./README.md).

**Agent (AI)** — a narrow, purpose-scoped class implementing `Laravel\Ai\Contracts\Agent` (e.g. `GuideAssistantAgent`) with an explicit system prompt and resource bounds. See [ADR-017](./adr-017-ai-architecture.md). Not to be confused with an AI coding agent (Claude Code, Cursor) working on the codebase itself.

**Audit log / `audit_logs`** — the universal, automatic record of `created`/`updated`/`deleted` mutations on audited models, written by `AuditObserver`. See [ADR-011](./adr-011-audit-history.md).

**Branch** — a physical or logical retail location within a tenant (Phase 3). Has its own inventory, pricing overrides, and settings. Resolved per-request by `SetBranchContext` middleware into `BranchContext`.

**BranchContext** — the request-lifecycle singleton (`app/Support/BranchContext.php`) holding the currently active branch. The architectural precedent for `TenantContext` (post-Phase-28). See [ADR-002](./adr-002-modular-monolith.md).

**Data mart** — a nightly-ETL'd, denormalized, aggregate-shaped set of tables (`data_mart_sales`, `data_mart_inventory`, `data_mart_ar_aging`) built specifically for analytical/BI queries, kept separate from live OLTP tables. See [ADR-016](./adr-016-reporting-bi.md).

**Domain event** — a Laravel event representing something that already happened in the business domain (`SaleCompleted`, `EmployeeCreated`), dispatched by a Service and consumed by decoupled Listeners. See [ADR-005](./adr-005-domain-events.md).

**DTO (Data Transfer Object)** — a typed, usually `readonly`, class carrying data between layers (Service ↔ Repository, Service ↔ Service) without exposing a `Request` or Eloquent model's shape across the boundary. See [ADR-003](./adr-003-backend-architecture.md).

**Event slug** — a stable, dot-separated string identifier for a domain event (`po.created`, `leave_request.submitted`) distinct from its PHP class name, used as the public contract for webhooks and workflow triggers. See [ADR-005](./adr-005-domain-events.md).

**Extension point** — one of the five sanctioned seams (domain events, webhook registry, workflow definitions, navigation registry, module registration) through which a module or future plugin can hook into RetailPulse without violating module boundaries. See [ADR-009](./adr-009-plugin-system.md).

**Integration Hub** — the collective name for RetailPulse's inbound webhook receivers and outbound webhook registry/provider-adapter mechanism connecting to external systems (Shopify, WooCommerce, payment gateways, WhatsApp, etc.). See [ADR-007](./adr-007-integration-hub.md).

**Modular monolith** — RetailPulse's architecture: one deployable application, internally organized into domain modules with enforced boundaries, sharing one database. See [ADR-002](./adr-002-modular-monolith.md).

**Module** — a vertical slice of one business domain (migrations, models, repositories, services, policies, controllers, events, frontend pages, tests) mirroring the SRS domain list (e.g. HR, Procurement, Loyalty). See [ADR-002](./adr-002-modular-monolith.md).

**OLTP (Online Transaction Processing)** — the live, transactional database schema that the application reads/writes during normal operation (sales, inventory, HR records), as distinct from the [data mart](#data-mart) used for analytics.

**Phase** — a sequential, shippable slice of the roadmap (`docs/phases/phase-NN-*.md`), each covering a bounded set of SRS sections with its own data model, services, and acceptance criteria.

**Plan (SaaS)** — a subscription tier (e.g. Starter, Retail Pro, Enterprise) determining which modules and usage limits a tenant has access to (Phase 28). Sits between tenant override and module default in the four-tier configuration hierarchy. See [ADR-009](./adr-009-plugin-system.md).

**Policy** — a Laravel authorization class (`app/Policies/`), one per authorizable resource, the single source of truth for "may this user perform this action." See [ADR-010](./adr-010-security.md).

**Reversal / adjustment record** — the mechanism for correcting a finalized financial/HR record: a new, linked record (a reversing journal entry, a credit note, a payslip adjustment) rather than an edit of the original. See [ADR-011](./adr-011-audit-history.md).

**Service** — the layer (`app/Services/{Domain}/`) owning business logic: orchestration, rule enforcement, transaction boundaries, and event dispatch. See [ADR-003](./adr-003-backend-architecture.md).

**SRS (Software Requirements Specification)** — `docs/srs.md`, the document defining *what* RetailPulse must do, organized by numbered section (e.g. §3.30 Workflow Engine) referenced throughout phase docs and ADRs.

**Tenant** — a customer organization using RetailPulse's shared SaaS instance. Nullable `tenant_id` columns already exist on tenant-owned tables ([tenant-schema-preparation.md](./tenant-schema-preparation.md)); row-level isolation via `TenantScope` / `TenantContext` is Phase 28. See [ADR-001](./adr-001-saas-multi-tenancy.md).

**TenantContext** — the (post-Phase-28) request-lifecycle singleton holding the resolved active tenant, mirroring `BranchContext`. See [ADR-001](./adr-001-saas-multi-tenancy.md).

**TenantScope** — the (post-Phase-28) global Eloquent scope enforcing row-level tenant isolation on every tenant-owned model. See [ADR-001](./adr-001-saas-multi-tenancy.md).

**Webhook registry** — the mechanism by which a business registers a URL, secret, and subscribed event slugs to receive signed outbound POSTs when matching domain events occur. See [ADR-007](./adr-007-integration-hub.md).

**Workflow definition** — a declarative, JSON-configured multi-step approval chain (trigger event, conditions, ordered steps with assignee/SLA/timeout behavior) that a business activates without a code change. See [ADR-006](./adr-006-workflow-engine.md).

**Workflow instance** — one running approval process against a specific triggering entity (e.g. one purchase order awaiting approval), advanced via `WorkflowEngine::act()`. See [ADR-006](./adr-006-workflow-engine.md).
