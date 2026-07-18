# ADR-008: Public REST API Strategy

Status: Accepted

Date: 2026-07-19

Related: [ADR-007 Integration Hub](./adr-007-integration-hub.md) · [ADR-010 Security Principles](./adr-010-security-principles.md) · [Phase 15 — API, Webhooks & Integrations](../phases/phase-15-api-integrations.md)

---

# Context

RetailPulse already has an internal API surface (`routes/api.php`, `/api/v1/...`) used by its own React/Inertia frontend for POS, checkout, and search — session-based, `web` + `auth` middleware, not token auth (see CLAUDE.md's Phase 8 checkout flow). Phase 15 requires a genuinely **external-developer-facing** API on top of the same domain, with its own authentication, versioning, and quota model, so third parties (a partner integration, a customer's own script, an external BI tool) can read and write RetailPulse data without session cookies or admin UI access.

These are two different consumers of similar-looking `/api/v1/...` routes and must not be conflated: the internal, session-based routes exist to serve RetailPulse's own frontend and are not a stable external contract; the public API described here is the external contract.

# Decision

## API-first philosophy for anything meant to be external

Any capability RetailPulse wants a third party (partner, customer script, external BI/reporting tool) to use must be exposed as a documented, token-authenticated public API endpoint — not reverse-engineered from the internal Inertia routes. Internal admin/session routes may change shape freely as the admin UI evolves; the public API may not, once versioned and published (see Versioning below). If a feature is meant to be externally consumable, design its public API surface deliberately, don't just "let people call the internal routes with a cookie."

## Authentication

Public API authentication is **Laravel Sanctum personal access tokens with scoped abilities** (already the mechanism named in Phase 15 and referenced in CLAUDE.md), distinct from the session-based `web` + `auth` guard used by the internal `/api/v1/...` routes consumed by the Inertia frontend:

- A token is issued with explicit abilities (e.g. `sales:read`, `products:import`) — an endpoint checks the token's ability, not just "is authenticated," per request.
- Admin UI provides token creation/revocation (Phase 15) — tokens are never generated or shared out-of-band without an audit trail entry ([ADR-005](./adr-005-audit-trail.md)).
- A token scoped to `sales:read` must not be able to create a user or touch any resource outside its granted abilities — this is the acceptance criterion already stated in Phase 15 and is non-negotiable for any new public endpoint.

## Versioning

- URI-based versioning: `/api/v1/...`, with a new `/api/v2/...` introduced only for breaking changes, not for every release.
- A field addition, a new optional endpoint, or a new event slug is **not** a breaking change and does not require a version bump.
- Removing a field, changing a field's type/meaning, or changing an endpoint's required parameters **is** breaking and requires either a new version or an explicit, communicated deprecation window — never a silent change to a published `v1` contract.
- Once Phase 28 multi-tenancy lands, the public API's tenant resolution (subdomain or explicit tenant header, per [ADR-001](./adr-001-saas-multi-tenancy.md)) is itself part of the versioned contract — do not change how a token resolves its tenant without a version bump.

## Rate limiting and quotas

Per-token quotas (requests/minute + burst, per Phase 15 SRS v4.0 enhancement) are enforced independently of, and in addition to, general per-IP rate limiting — a compromised or misbehaving token must not be able to degrade service for other tenants/tokens sharing the platform. Quota state is visible to the token owner (usage dashboard) so a legitimate integrator can self-diagnose a `429` rather than filing a support ticket.

## Documentation

The public API is described by machine-readable OpenAPI/Swagger documentation (per Phase 15 — e.g. Scramble or L5-Swagger), generated from or validated against the actual route/request definitions, not hand-maintained prose that drifts from the code. A new public endpoint is not complete until it appears in this generated documentation.

## External developer support

- Import/export endpoints (`/api/v1/{resource}/import`, `/export`) follow the same validate-preview → confirm → queued-processing pattern as the admin bulk import UI (`app/Services/ImportExport/`) — external integrators get the same safety guarantees (row-level validation errors, no partial silent failure) as internal admin users, not a stripped-down version.
- Webhook events delivered to external subscribers ([ADR-007](./adr-007-integration-hub.md)) use the same event-slug vocabulary as the public API's resource naming, so an integrator reading the API docs and the webhook payload docs sees one consistent model of RetailPulse's domain, not two.

# Consequences

- Internal Inertia/session routes and the external public API can evolve independently — a refactor of an admin page's props does not require a public API version bump, and vice versa.
- Third-party integrators get a stable, documented, scoped-token contract they can build against without needing admin session access.
- Any endpoint added "quickly" without a token-ability check or without appearing in the OpenAPI docs is a defect against this ADR, not an acceptable shortcut — flag it rather than shipping it silently.
