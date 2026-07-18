# ADR-009: Plugin Architecture

Status: Accepted

Date: 2026-07-19

Related: [ADR-002 Modular Architecture](./adr-002-modular-architecture.md) · [ADR-001 SaaS Multi-Tenancy](./adr-001-saas-multi-tenancy.md) · [Phase 23 — Module Config Engine](../phases/phase-23-module-config-engine.md)

---

# Context

Not every tenant needs every module — a pure retail business has no use for the restaurant/KOT module (Phase 19–20); a single-branch shop has no use for intercompany accounting. Bundling every module as always-on bloats the UI with irrelevant navigation and settings for most tenants, and blocks the long-term vision (stated in ADR-001) of a plugin ecosystem / marketplace where third parties can eventually extend RetailPulse. Phase 23 (Module Config Engine) is the concrete near-term deliverable; this ADR is the architectural commitment that makes it — and what comes after it — coherent.

# Decision

## Modules are enabled/disabled, not compiled in or out

Per Phase 23, a `modules` registry table plus `tenant_modules` (post-Phase-28) or an equivalent per-installation table describes which of RetailPulse's built-in domain modules ([ADR-002](./adr-002-modular-architecture.md)) are active. A `CheckModuleEnabled` middleware family (already named as `EnsureHrModuleEnabled`, `EnsureAccountingModuleEnabled` in the current codebase, ahead of the full Phase 23 registry) gates routes; the admin sidebar (`NavigationRegistry`/`AdminNavigationCatalog`) reflects enabled modules dynamically rather than always listing every module.

This means every module must be **structurally capable of being off**: its migrations create tables regardless (shared schema, per [ADR-001](./adr-001-saas-multi-tenancy.md)), but its routes, navigation entries, and any cross-module event listeners must degrade gracefully to a no-op when the module is disabled for a given tenant — not throw, not silently corrupt data for other enabled modules.

## Four-tier configuration hierarchy

Per Phase 23/SRS §3.25, configuration resolves in this order (most to least specific): tenant override → plan default (post-Phase-28) → module default → system default. This is the general mechanism for "should this be on, and with what settings" for any module or feature flag going forward — do not introduce a fifth, feature-specific configuration resolution scheme; extend this hierarchy.

## Extension points

The architectural extension points a "plugin" (first-party module or, eventually, third-party) can hook into are the same seams already established elsewhere in these ADRs — this ADR does not invent new ones, it names them as the plugin surface:

- **Domain events** ([ADR-003](./adr-003-domain-events.md)) — a plugin listens to existing event slugs to react to core business actions without core code depending on the plugin.
- **Webhook registry** ([ADR-007](./adr-007-integration-hub.md)) — a plugin (or external tool) subscribes to events without even living inside the codebase.
- **Workflow definitions** ([ADR-006](./adr-006-workflow-engine.md)) — a plugin can register a new approval workflow definition without touching the engine.
- **Navigation registry** (`NavigationRegistry`, `AdminNavigationCatalog`) — a plugin registers its own nav sections/items rather than the core navigation catalog hardcoding every module's entries inline.
- **Module registration** (Phase 23 registry) — a plugin declares itself as a module with a slug, so it participates in the enable/disable and configuration-tier mechanisms above like any built-in module.

## Module registration is declarative

A module (built-in or, in the marketplace future, third-party) registers itself — slug, display name, required permissions, navigation contributions, dependencies on other modules — rather than being wired in through scattered conditionals across the codebase. `docs/phases/phase-12/module-registry.md` is the existing precedent for this pattern within HR; Phase 23 generalizes it platform-wide.

## Future marketplace vision — what this ADR commits to now vs. later

This ADR commits the codebase to the *shape* that makes a marketplace possible later (declarative module registration, event/webhook/workflow extension points, tiered configuration) — it does **not** commit to building third-party plugin sandboxing, a plugin package format, a marketplace storefront, or arbitrary third-party code execution now. Those are future scope, gated behind their own future ADR when actually scheduled. Do not build speculative plugin-sandboxing infrastructure ahead of that decision; the near-term, real deliverable is Phase 23's first-party module toggle system.

# Consequences

- A tenant only sees navigation, settings, and functionality for modules relevant to their business — no per-tenant UI clutter from unused verticals (restaurant, manufacturing, etc.).
- New built-in modules are added the same way from day one, so there is no "core modules are special-cased, later modules are bolted on" divergence to unwind when the marketplace is eventually built.
- Every module owner must consider "what happens when this module is disabled for a tenant" as part of building it, not as an afterthought once Phase 23 ships.
