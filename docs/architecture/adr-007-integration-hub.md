# ADR-007: Integration Hub

Status: Accepted

Date: 2026-07-19

Related: [ADR-008 Public API](./adr-008-public-api.md) · [ADR-005 Domain Events](./adr-005-domain-events.md) · [ADR-006 Workflow Engine](./adr-006-workflow-engine.md) · [ADR-016 Reporting & BI](./adr-016-reporting-bi.md) · [Phase 15 — API, Webhooks & Integrations](../phases/phase-15-api-integrations.md) · [Phase 25 — E-Commerce Integration](../phases/phase-25-ecommerce-integration.md)

---

## Why

RetailPulse's SRS calls for connecting to a wide, and growing, set of external systems: Shopify, WooCommerce, TikTok Shop, WhatsApp, payment gateways (Stripe, PayPal, JazzCash, EasyPaisa), SMS/email providers, accounting tools (Xero/QuickBooks), and BI tools (Power BI, Tableau). Businesses also increasingly wire up their own automation via tools like n8n or Zapier. Without a deliberate strategy, "integrations" becomes an unbounded pile of one-off HTTP clients scattered across controllers, each with its own auth handling, retry logic, and failure mode — and every new integration request turns into bespoke core-application code.

## What

RetailPulse owns its business logic; external tools orchestrate integrations on top of stable, versioned extension points RetailPulse exposes.

## How

### Two directions of integration, two mechanisms

**Inbound (external system → RetailPulse):** dedicated webhook receivers per provider (`POST /api/v1/webhooks/shopify/orders`, `POST /api/v1/webhooks/woocommerce/orders`, per Phase 15/25). Each receiver:
- Verifies the provider's signature (HMAC for Shopify, JWT for WooCommerce) before trusting the payload.
- Responds `200 OK` immediately and does the actual work (`ShopifyOrderPullJob`, `ShopifyProductSyncJob`) on a queue — inbound webhook receivers must never do synchronous, provider-blocking work in the request path.
- Translates the provider's payload into RetailPulse's own domain actions (e.g. a Shopify order becomes a POS cart via the normal cart-creation path) — the provider's data model does not leak past the translation boundary into core services.

**Outbound (RetailPulse → external system):** RetailPulse's own **webhook registry** (Phase 15) — a business registers a URL, a secret, and the event slugs it wants (`order.created`, `refund.processed`, `po.approved`, etc., using the same slug convention as [ADR-005](./adr-005-domain-events.md)). A delivery job signs (HMAC) and POSTs with retries. This is the generic mechanism through which *any* external tool — n8n, Zapier, a custom script, a partner's own backend — subscribes to what happens inside RetailPulse, without RetailPulse needing to know that tool exists.

### RetailPulse owns business logic; external tools orchestrate

This is the load-bearing distinction for every integration decision:

- **RetailPulse decides and executes** what a sale, a refund, a stock movement, a PO approval *means* and *does* to its own data — tax calculation, inventory deduction, ledger posting, workflow approval ([ADR-006](./adr-006-workflow-engine.md)). No external orchestrator is ever the source of truth for a RetailPulse business rule, and no integration should require an external system to be reachable for a core transaction to complete (see `fbr.failure_mode = queue`, Phase 8, as the existing precedent: external dependency failure degrades gracefully, it doesn't block the sale).
- **External tools (n8n, Zapier, a partner's iPaaS, a bespoke script) may sequence, filter, and fan out** RetailPulse's own outbound webhook events into whatever the business wants downstream — a Slack notification, a Google Sheet row, a CRM update. RetailPulse does not need to know or care what a subscriber does with an event once delivered.
- Consequently: **RetailPulse does not adopt n8n (or any external orchestrator) as internal infrastructure.** It is a valid *consumer* of RetailPulse's webhook registry that some businesses may run themselves; it is never where RetailPulse's own approval logic, tax logic, or posting logic lives — that would violate [ADR-003](./adr-003-backend-architecture.md)'s layering and make core business behavior depend on an external system's uptime and configuration.

### Provider adapters are swappable, not hardcoded

Payment gateways, fiscal reporting (FBR), and future accounting connectors all follow the same shape already established by `FbrReportingService` and the `payment_gateway_configs` table: an interface/service the core app calls, with the concrete provider selected by configuration (`system_settings` / per-branch config), not by an `if ($provider === 'stripe')` branch scattered through business logic. The Phase 15 doc's `FiscalProviderInterface` is the pattern to replicate for every new external system category — new provider, new adapter class, no change to the calling code's contract.

### E-commerce sync specifics (Shopify / WooCommerce / TikTok Shop)

Product and inventory sync is one-way-of-truth per field: RetailPulse is authoritative for inventory levels (pushed out), the external channel is authoritative for order creation (pulled in) — this avoids the classic double-sync conflict where both systems think they own the same number. TikTok Shop follows the same adapter shape as Shopify/WooCommerce (product push, order pull, signed webhook receiver) rather than a bespoke integration pattern per channel. Customer merge (Phase 25) resolves identity by verified contact match (email/phone), never by silently overwriting an existing RetailPulse customer record from channel data without a merge decision.

### WhatsApp and communications

WhatsApp (Cloud API), SMS (Twilio), and email (SendGrid/Mailgun) are outbound communication providers behind the same "swappable adapter selected by config" pattern as payments — a business chooses its provider in Settings; the calling code (invoice sharing, notifications) never hardcodes a specific vendor's SDK call inline.

### Accounting system connectors (Xero / QuickBooks)

Xero/QuickBooks connectors (Phase 15 stub) are outbound-sync integrations: RetailPulse's own chart of accounts and journal postings ([ADR-011](./adr-011-audit-history.md)) remain authoritative; the connector pushes summarized postings out, it does not pull the external system's ledger back in as a competing source of truth for RetailPulse's own books.

### Analytics & BI export (Power BI, Tableau)

Power BI/Tableau connectivity is an **outbound, read-only** integration: external BI tools consume RetailPulse's data mart ([ADR-016](./adr-016-reporting-bi.md)), not its live transactional schema — this ADR governs *that a connector exists and behaves like every other outbound integration* (versioned, credential-scoped, no write path back into RetailPulse); [ADR-016](./adr-016-reporting-bi.md) governs the data model it reads from. Do not point an external BI tool directly at production OLTP tables — that couples reporting query load to transactional performance and bypasses the data mart's intentional shaping of the data for analytics use.

## Trade-offs

- **A generic webhook registry is less turnkey per-provider than a bespoke, provider-specific integration would be** (e.g. Shopify's own app-install flow) — accepted because a generic mechanism scales to N future providers without N bespoke subsystems; provider-specific nuance (signature scheme, payload shape) lives in the adapter, not in the registry mechanism itself.
- **Asynchronous webhook processing means eventual consistency with external systems** — an inbound order pull is not reflected in RetailPulse until its queued job runs. Accepted because synchronous processing in the request path would make RetailPulse's webhook endpoint availability hostage to its own queue depth, and because e-commerce order sync does not require sub-second consistency.
- **RetailPulse cannot control what a business does with the Integration Hub** (e.g. wiring a webhook into a fragile n8n workflow) — accepted as the correct boundary: RetailPulse's responsibility ends at reliably delivering a signed, well-formed event; what happens downstream is the business's own operational concern.

## Alternatives considered

- **A dedicated integration microservice** — rejected for now per [ADR-002](./adr-002-modular-monolith.md)'s reasoning: no concrete scaling pain justifies splitting this out of the monolith yet; the webhook registry and adapters are ordinary modules within it.
- **Deep, bespoke, point-to-point integrations per provider** (a Shopify-specific sync engine unrelated in shape to the WooCommerce one) — rejected: it multiplies the surface area to maintain and test per provider and makes onboarding a new channel (TikTok Shop, a future marketplace) a from-scratch project instead of "implement this interface."
- **Depending on n8n/Zapier as the primary integration layer for first-party channels** (i.e., RetailPulse ships without its own Shopify/WooCommerce sync and expects businesses to wire it themselves via automation tools) — rejected: first-party e-commerce sync is core SRS scope (Phase 25) businesses expect out of the box; automation tools are the extension mechanism for what RetailPulse doesn't build in, not a substitute for it.

## Future direction

Every new external system category (a new payment gateway, a new e-commerce channel, a new BI tool) is expected to slot into one of the two mechanisms above (inbound webhook receiver, outbound webhook registry + swappable adapter) rather than introducing a third integration pattern. As the number of registered outbound subscribers per tenant grows, delivery reliability (retry/backoff, dead-letter handling for permanently failing endpoints) is expected to mature within the existing delivery-job mechanism, not be replaced by a different one.

## Impact on future development

- Adding a new external integration means: define the event slugs it needs (already exist per [ADR-005](./adr-005-domain-events.md) if the underlying business event exists), add a provider adapter behind an interface, and if inbound, add a signed webhook receiver that queues work. It does not mean adding branching logic to core services.
- A business can wire RetailPulse into n8n, Zapier, or a custom integration purely through the webhook registry and public API ([ADR-008](./adr-008-public-api.md)) — no core code change required, and no risk that an external tool's downtime affects a checkout or a payroll run.
- If a future requirement seems to need core business logic to live inside an external orchestrator, that is a signal the requirement has been mis-scoped as an integration when it is actually a Workflow Engine ([ADR-006](./adr-006-workflow-engine.md)) or core-service requirement — resolve the ambiguity before building, not after.
