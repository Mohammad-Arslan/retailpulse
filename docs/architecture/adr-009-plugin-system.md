# ADR-009: Plugin System

Status: Accepted

Date: 2026-07-19

Related: [ADR-002 Modular Monolith](./adr-002-modular-monolith.md) · [ADR-001 SaaS Multi-Tenancy](./adr-001-saas-multi-tenancy.md) · [ADR-005 Domain Events](./adr-005-domain-events.md) · [Phase 23 — Module Config Engine](../phases/phase-23-module-config-engine.md)

---

## Why

Not every tenant needs every module — a pure retail business has no use for the restaurant/KOT module (Phase 19–20); a single-branch shop has no use for intercompany accounting. Bundling every module as always-on bloats the UI with irrelevant navigation and settings for most tenants, and blocks the long-term vision ([`docs/vision.md`](../vision.md)) of a plugin ecosystem / marketplace where third parties can eventually extend RetailPulse. Phase 23 (Module Config Engine) is the concrete near-term deliverable; this ADR is the architectural commitment that makes it — and what comes after it — coherent.

## What

Modules are **enabled/disabled at runtime per tenant**, not compiled in or out at build time, and extension happens through a small, fixed set of sanctioned extension points rather than modules reaching arbitrarily into each other.

## How

### Modules are enabled/disabled, not compiled in or out

Per Phase 23, a `modules` registry table plus `tenant_modules` (post-Phase-28) or an equivalent per-installation table describes which of RetailPulse's built-in domain modules ([ADR-002](./adr-002-modular-monolith.md)) are active. A `CheckModuleEnabled` middleware family (already named as `EnsureHrModuleEnabled`, `EnsureAccountingModuleEnabled` in the current codebase, ahead of the full Phase 23 registry) gates routes; the admin sidebar (`NavigationRegistry`/`AdminNavigationCatalog`) reflects enabled modules dynamically rather than always listing every module.

This means every module must be **structurally capable of being off**: its migrations create tables regardless (shared schema, per [ADR-001](./adr-001-saas-multi-tenancy.md)), but its routes, navigation entries, and any cross-module event listeners must degrade gracefully to a no-op when the module is disabled for a given tenant — not throw, not silently corrupt data for other enabled modules.

### Discovery

A module is discovered by the platform through its registry entry, not by filesystem scanning or convention-guessing at boot time — the Phase 23 `modules` table (or, pre-Phase-23, the hardcoded middleware/navigation wiring it will replace) is the single place the platform looks to know what modules exist. A module that isn't registered doesn't exist to the enable/disable, navigation, or configuration-tier mechanisms, even if its code is present in `app/` — this is deliberate: presence of code is not the same as the module being a first-class, toggleable citizen of the platform.

### Registration

Module registration is declarative: slug, display name, required permissions, navigation contributions, and dependencies on other modules are declared data, not scattered conditionals across the codebase (`if ($moduleSlug === 'hr') { ... }` in ten different files). A module registering a dependency on another module (e.g. the future Recipe & Ingredients module depending on Inventory) is validated at registration/activation time — activating a module whose declared dependency isn't active is rejected with a clear error, not left to fail confusingly at first use. `docs/phases/phase-12/module-registry.md` is the existing precedent for this pattern within HR; Phase 23 generalizes it platform-wide.

### Four-tier configuration hierarchy

Per Phase 23/SRS §3.25, configuration resolves in this order (most to least specific): tenant override → plan default (post-Phase-28) → module default → system default. This is the general mechanism for "should this be on, and with what settings" for any module or feature flag going forward — do not introduce a fifth, feature-specific configuration resolution scheme; extend this hierarchy.

### Extension points

The architectural extension points a "plugin" (first-party module or, eventually, third-party) can hook into are the same seams already established elsewhere in these ADRs — this ADR does not invent new ones, it names them as the plugin surface:

- **Domain events** ([ADR-005](./adr-005-domain-events.md)) — a plugin listens to existing event slugs to react to core business actions without core code depending on the plugin.
- **Webhook registry** ([ADR-007](./adr-007-integration-hub.md)) — a plugin (or external tool) subscribes to events without even living inside the codebase.
- **Workflow definitions** ([ADR-006](./adr-006-workflow-engine.md)) — a plugin can register a new approval workflow definition without touching the engine.
- **Navigation registry** (`NavigationRegistry`, `AdminNavigationCatalog`) — a plugin registers its own nav sections/items rather than the core navigation catalog hardcoding every module's entries inline.
- **Module registration** (Phase 23 registry, above) — a plugin declares itself as a module with a slug, so it participates in the enable/disable and configuration-tier mechanisms above like any built-in module.

A capability that cannot be built using one of these five extension points is not yet supported by the plugin system — that is a signal to raise a new ADR (or amend this one) defining the new extension point deliberately, not to bypass module boundaries ([ADR-002](./adr-002-modular-monolith.md)) as a workaround.

### Marketplace and licensing — future scope, explicitly not built yet

This ADR commits the codebase to the *shape* that makes a marketplace possible later (declarative module registration, event/webhook/workflow extension points, tiered configuration) — it does **not** commit to building, now:

- Third-party plugin sandboxing or arbitrary third-party code execution.
- A plugin package format or distribution mechanism.
- A marketplace storefront, billing split, or review/approval pipeline for third-party submissions.
- A licensing model for paid plugins (revenue share, per-tenant plugin licensing, trial periods).

These are real, anticipated future scope — the vision document's decade-long horizon assumes a marketplace exists eventually — but each is its own future ADR when actually scheduled, because each carries its own hard security and business questions (what can third-party code touch; who is liable when it misbehaves; how is a tenant protected from a malicious or buggy plugin) that deserve a dedicated decision, not an assumption baked in here. Do not build speculative plugin-sandboxing or licensing infrastructure ahead of that decision; the near-term, real deliverable is Phase 23's first-party module toggle system.

## Trade-offs

- **The five extension points are deliberately narrow** — a legitimate extension need that doesn't fit one of them cannot be satisfied without a new ADR. Accepted because an unbounded set of ad hoc extension mechanisms is exactly how plugin systems become unmaintainable; a small, well-understood set of seams is easier to secure and reason about, especially once third parties are involved.
- **First-party modules pay the same "must degrade gracefully when disabled" cost as future third-party plugins would** — there is no shortcut for built-in modules. Accepted because it's the only way to guarantee the marketplace future doesn't require retrofitting every existing module's assumptions about always being on.

## Alternatives considered

- **Feature flags only, no formal module registry** — rejected: a flat feature-flag list doesn't capture module dependencies, navigation contributions, or per-plan bundling the way a structured module registry does, and doesn't give the marketplace vision a coherent unit ("a module") to eventually package and sell.
- **Compile-time module selection (separate builds per module combination)** — rejected: defeats the single shared SaaS instance model ([ADR-001](./adr-001-saas-multi-tenancy.md)) by requiring a tenant to be on a specific build; runtime enable/disable against one shared codebase is required for the shared-instance strategy to work at all.
- **Building third-party plugin support now, ahead of first-party module toggling** — rejected: first-party module toggling (Phase 23) is validated, lower-risk scope that exercises the same extension points a marketplace would need, without the security exposure of running third-party code before the platform has the isolation story to do so safely.

## Future direction

Once first-party module toggling (Phase 23) has been in production use long enough to validate the extension points above under real module combinations, a dedicated future ADR addresses third-party plugin packaging, sandboxing, marketplace mechanics, and licensing — informed by which extension points turned out to be sufficient and which needed to grow.

## Impact on future development

- A tenant only sees navigation, settings, and functionality for modules relevant to their business — no per-tenant UI clutter from unused verticals (restaurant, manufacturing, etc.).
- New built-in modules are added the same way from day one, so there is no "core modules are special-cased, later modules are bolted on" divergence to unwind when the marketplace is eventually built.
- Every module owner must consider "what happens when this module is disabled for a tenant" as part of building it, not as an afterthought once Phase 23 ships.
