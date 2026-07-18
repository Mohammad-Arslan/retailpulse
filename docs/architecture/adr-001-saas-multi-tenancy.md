# ADR-001: SaaS Multi-Tenancy Strategy

Status: Accepted

Date: 2026-07-18

Related: [ADR-002 Modular Architecture](./adr-002-modular-architecture.md) · [ADR-010 Security Principles](./adr-010-security-principles.md) · [Phase 28 — SaaS Multi-Tenancy](../phases/phase-28-saas-multitenancy.md)

---

# Context

RetailPulse is being built as a modern SaaS-first ERP platform.

Although early development operates in a single-tenant mode, the long-term architecture is designed to support thousands of organizations using a single application instance.

To avoid expensive schema migrations later, tenant ownership is introduced gradually during development while the actual multi-tenancy infrastructure is deferred until Phase 29.

---

# Decision

RetailPulse will adopt the following SaaS architecture:

- Shared Database
- Shared Schema
- Row-Level Isolation using `tenant_id`

Each customer (tenant) shares the same database while business data is logically isolated through `tenant_id`.

Phase 29 will implement the complete multi-tenancy infrastructure.

---

# Phase Strategy

## Before Phase 29

Application continues operating as a single-tenant ERP.

Schema is gradually prepared by adding nullable `tenant_id` columns where appropriate.

No tenant filtering is enforced.

No tenant middleware exists.

No tenant resolver exists.

No tenant security is implemented.

The purpose is only to reduce future migration complexity.

---

## Phase 29

Phase 29 will implement:

- Tenant Resolver
- Tenant Context
- Global Tenant Scope
- Tenant Middleware
- Tenant Authorization
- Tenant-aware Validation
- Tenant-aware Queues
- Tenant-aware Cache
- Tenant-aware Storage
- Tenant-aware Search
- Tenant-aware Broadcasting
- Tenant Provisioning
- Tenant Lifecycle Management
- Cross-Tenant Data Protection

After Phase 29, tenant isolation becomes mandatory.

---

# Current State in Codebase

As of this writing (pre-Phase-28), the "Before Phase 29" prep strategy above is already underway: 11 migrations include a nullable `tenant_id` column (e.g. `extend_users_table`, `create_branches_table`, `create_product_catalog_tables`, `create_products_tables`) added ahead of schedule so the columns don't require a disruptive backfill later. There is:

- No `tenants` table yet.
- No `TenantContext`, `TenantScope`, or `SetTenantContext` middleware yet.
- No tenant filtering enforced anywhere.

This is expected and correct per the phase strategy below — do not treat the presence of `tenant_id` columns as evidence that isolation is enforced, and do not add tenant-filtering logic ahead of Phase 28/29 without an explicit decision to pull that scope forward.

---

# Tenant Classification Rules

## Category 1 — Tenant Root

Defines ownership.

Examples:

- Organizations
- Organization Entities
- Branches
- Users (if tenant owned)

Must contain `tenant_id`.

---

## Category 2 — Tenant-Owned Business Data

Every business record owned by a tenant must contain a direct `tenant_id`, even if ownership could be derived through joins.

Examples:

- Accounting
- HR
- Payroll
- Inventory
- Procurement
- Sales
- CRM
- Manufacturing
- Assets
- POS
- Loyalty
- Projects
- Helpdesk

Reason:

- Safer tenant isolation
- Better indexing
- Faster reporting
- Easier sharding
- Easier exports
- Easier backups
- Simpler future migrations

---

## Category 3 — Platform Infrastructure

Never contains `tenant_id`.

Examples:

- migrations
- jobs
- failed_jobs
- cache
- sessions
- password reset tokens

---

## Category 4 — Global Reference Data

Never contains `tenant_id`.

Examples:

- Countries
- Currencies
- Languages
- Timezones
- Units of Measure

These records are shared by all tenants.

---

# Pivot Tables

Do not automatically add `tenant_id`.

Only add it when the table behaves as an independent business entity or is queried independently.

---

# Development Guidelines

Every new migration must classify the table before creation.

Ask:

1. Is this business data owned by one tenant?
2. Is this platform infrastructure?
3. Is this shared reference data?
4. Is this an independent business entity?

Only tenant-owned business data should receive `tenant_id`.

---

# Long-Term Vision

RetailPulse is designed to support:

- Thousands of tenants
- Millions of users
- Shared database architecture
- Horizontal scaling
- Event-driven integrations
- Plugin ecosystem
- Enterprise deployments
- Dedicated customer deployments when required

This document is the authoritative source for RetailPulse's SaaS multi-tenancy architecture.

All future phases and AI-assisted development must comply with this decision.
