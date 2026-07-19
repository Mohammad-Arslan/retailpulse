# ADR-008: Public REST API Strategy

Status: Accepted

Date: 2026-07-19

Related: [ADR-007 Integration Hub](./adr-007-integration-hub.md) · [ADR-010 Security](./adr-010-security.md) · [ADR-001 SaaS Multi-Tenancy](./adr-001-saas-multi-tenancy.md) · [Phase 15 — API, Webhooks & Integrations](../phases/phase-15-api-integrations.md)

---

## Why

RetailPulse already has an internal API surface (`routes/api.php`, `/api/v1/...`) used by its own React/Inertia frontend for POS, checkout, and search — session-based, `web` + `auth` middleware, not token auth (see the Phase 8 checkout flow). Phase 15 requires a genuinely **external-developer-facing** API on top of the same domain, with its own authentication, versioning, and quota model, so third parties (a partner integration, a customer's own script, an external BI tool) can read and write RetailPulse data without session cookies or admin UI access.

These are two different consumers of similar-looking `/api/v1/...` routes and must not be conflated: the internal, session-based routes exist to serve RetailPulse's own frontend and are not a stable external contract; the public API described here is the external contract.

## What

Any capability RetailPulse wants a third party (partner, customer script, external BI/reporting tool) to use must be exposed as a documented, token-authenticated public API endpoint — not reverse-engineered from the internal Inertia routes. Internal admin/session routes may change shape freely as the admin UI evolves; the public API may not, once versioned and published. If a feature is meant to be externally consumable, design its public API surface deliberately, don't just "let people call the internal routes with a cookie."

## How

### Authentication

Public API authentication is **Laravel Sanctum personal access tokens with scoped abilities** (the mechanism named in Phase 15), distinct from the session-based `web` + `auth` guard used by the internal `/api/v1/...` routes consumed by the Inertia frontend:

- A token is issued with explicit abilities (e.g. `sales:read`, `products:import`) — an endpoint checks the token's ability, not just "is authenticated," per request.
- Admin UI provides token creation/revocation (Phase 15) — tokens are never generated or shared out-of-band without an audit trail entry ([ADR-011](./adr-011-audit-history.md)).
- A token scoped to `sales:read` must not be able to create a user or touch any resource outside its granted abilities — this is the acceptance criterion already stated in Phase 15 and is non-negotiable for any new public endpoint.

### Versioning

- URI-based versioning: `/api/v1/...`, with a new `/api/v2/...` introduced only for breaking changes, not for every release.
- A field addition, a new optional endpoint, or a new event slug is **not** a breaking change and does not require a version bump.
- Removing a field, changing a field's type/meaning, or changing an endpoint's required parameters **is** breaking and requires either a new version or an explicit, communicated deprecation window — never a silent change to a published `v1` contract.
- Once Phase 28 multi-tenancy lands, the public API's tenant resolution (subdomain or explicit tenant header, per [ADR-001](./adr-001-saas-multi-tenancy.md)) is itself part of the versioned contract — do not change how a token resolves its tenant without a version bump.

### Rate limiting and quotas

Per-token quotas (requests/minute + burst, per Phase 15 SRS v4.0 enhancement) are enforced independently of, and in addition to, general per-IP rate limiting — a compromised or misbehaving token must not be able to degrade service for other tenants/tokens sharing the platform. Quota state is visible to the token owner (usage dashboard) so a legitimate integrator can self-diagnose a `429` rather than filing a support ticket.

### Documentation

The public API is described by machine-readable OpenAPI/Swagger documentation (per Phase 15 — e.g. Scramble or L5-Swagger), generated from or validated against the actual route/request definitions, not hand-maintained prose that drifts from the code. A new public endpoint is not complete until it appears in this generated documentation.

### SDKs

Official client SDKs (starting with PHP and JavaScript/TypeScript, the two ecosystems most of RetailPulse's own integrators and partners already work in) are generated or hand-wrapped from the OpenAPI spec above, not maintained as a hand-written parallel definition of the API — an SDK that drifts from the actual contract is worse than no SDK, because it fails silently in a partner's codebase instead of obviously in ours. Until an official SDK exists for a given language, the OpenAPI spec plus documented auth/versioning here is the supported integration path — a partner writing their own thin HTTP client against the documented contract is a fully supported way to integrate, not a stopgap.

### Developer portal

A dedicated developer portal (API reference generated from OpenAPI, token self-service, usage/quota dashboard, changelog of version/deprecation notices) is the intended long-term home for external-developer support, superseding an admin-only token management screen once RetailPulse has enough external integrators to justify it. Until then, the Phase 15 admin UI's token management screen plus the generated OpenAPI docs are the supported minimum — do not block shipping public endpoints on the full portal existing.

### External developer support

- Import/export endpoints (`/api/v1/{resource}/import`, `/export`) follow the same validate-preview → confirm → queued-processing pattern as the admin bulk import UI (`app/Services/ImportExport/`) — external integrators get the same safety guarantees (row-level validation errors, no partial silent failure) as internal admin users, not a stripped-down version.
- Webhook events delivered to external subscribers ([ADR-007](./adr-007-integration-hub.md)) use the same event-slug vocabulary as the public API's resource naming, so an integrator reading the API docs and the webhook payload docs sees one consistent model of RetailPulse's domain, not two.

### Future GraphQL considerations

RetailPulse does not expose a GraphQL API today, and REST remains the default for every new public endpoint. GraphQL is a plausible future addition **specifically for read-heavy, deeply-nested query patterns** an external partner integration might want (e.g. a single query spanning product → variants → inventory → pricing across branches) where REST would otherwise require several round trips or a bespoke aggregate endpoint. If added, GraphQL would be introduced as an **additional** query surface alongside REST — not a replacement — scoped to genuinely read-heavy use cases, would reuse the exact same Sanctum token/ability authorization model (no separate auth scheme for GraphQL), and would not become a second place business logic is implemented (resolvers call the same Services as REST controllers do, per [ADR-003](./adr-003-backend-architecture.md)). This is not scheduled work; it is recorded here so a future decision to add it starts from an agreed set of constraints instead of an open design debate.

## Trade-offs

- **Two API surfaces to maintain conceptually** (internal Inertia routes, external public API) even where they touch the same underlying data — accepted because conflating them (letting external integrators hit session-authenticated internal routes) would make every admin-UI refactor a potential breaking change for external partners, which is a far worse ongoing cost.
- **Versioning discipline slows down "just add a field" changes** slightly (the field-addition-is-safe rule mitigates most of this, but a genuine breaking change requires the full deprecation-window process) — accepted as the cost of a contract external parties can build against without fear of silent breakage.
- **Per-token quotas add operational surface** (usage tracking, dashboards, 429 handling) beyond simple authentication — accepted because a single misbehaving integration must not be able to degrade the platform for every other tenant sharing it, especially post-Phase-28.

## Alternatives considered

- **No separate public API — external integrators use the same session-based routes as the frontend** — rejected: no scoped-ability model, no stable versioning contract, and couples external integrations to internal admin UI refactors. This is precisely the conflation this ADR exists to prevent.
- **GraphQL as the primary/only public API** — rejected as the default: most of RetailPulse's integration needs (webhooks, import/export, simple resource CRUD) are naturally REST-shaped, and GraphQL's main advantage (flexible nested queries) is a narrower, secondary need — see Future GraphQL Considerations above for where it may still fit.
- **API keys without scoped abilities (all-or-nothing token access)** — rejected: fails the Phase 15 acceptance criterion directly (a `sales:read` token must not reach user management) and is a materially weaker security posture for something meant to be handed to third parties.

## Future direction

As the number of external integrators grows, investment shifts from "does the API exist and is it documented" to developer experience (SDKs, the developer portal, richer OpenAPI tooling) and, if a concrete need emerges, the scoped GraphQL surface described above.

## Impact on future development

- Internal Inertia/session routes and the external public API can evolve independently — a refactor of an admin page's props does not require a public API version bump, and vice versa.
- Third-party integrators get a stable, documented, scoped-token contract they can build against without needing admin session access.
- Any endpoint added "quickly" without a token-ability check or without appearing in the OpenAPI docs is a defect against this ADR, not an acceptable shortcut — flag it rather than shipping it silently.
