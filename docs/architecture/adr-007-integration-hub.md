# ADR-007: Integration Hub

Status: Accepted

Date: 2026-07-19

Related: [ADR-008 Public API](./adr-008-public-api.md) · [ADR-003 Domain Events](./adr-003-domain-events.md) · [ADR-006 Workflow Engine](./adr-006-workflow-engine.md) · [Phase 15 — API, Webhooks & Integrations](../phases/phase-15-api-integrations.md) · [Phase 25 — E-Commerce Integration](../phases/phase-25-ecommerce-integration.md)

---

# Context

RetailPulse's SRS calls for connecting to a wide, and growing, set of external systems: Shopify, WooCommerce, TikTok Shop, WhatsApp, payment gateways (Stripe, PayPal, JazzCash, EasyPaisa), SMS/email providers, and accounting tools (Xero/QuickBooks). Businesses also increasingly wire up their own automation via tools like n8n or Zapier. Without a deliberate strategy, "integrations" becomes an unbounded pile of one-off HTTP clients scattered across controllers, each with its own auth handling, retry logic, and failure mode — and every new integration request turns into bespoke core-application code.

# Decision

RetailPulse owns its business logic; external tools orchestrate integrations on top of stable, versioned extension points RetailPulse exposes. Concretely:

## Two directions of integration, two mechanisms

**Inbound (external system → RetailPulse):** dedicated webhook receivers per provider (`POST /api/v1/webhooks/shopify/orders`, `POST /api/v1/webhooks/woocommerce/orders`, per Phase 15/25). Each receiver:
- Verifies the provider's signature (HMAC for Shopify, JWT for WooCommerce) before trusting the payload.
- Responds `200 OK` immediately and does the actual work (`ShopifyOrderPullJob`, `ShopifyProductSyncJob`) on a queue — inbound webhook receivers must never do synchronous, provider-blocking work in the request path.
- Translates the provider's payload into RetailPulse's own domain actions (e.g. a Shopify order becomes a POS cart via the normal cart-creation path) — the provider's data model does not leak past the translation boundary into core services.

**Outbound (RetailPulse → external system):** RetailPulse's own **webhook registry** (Phase 15) — a business registers a URL, a secret, and the event slugs it wants (`order.created`, `refund.processed`, `po.approved`, etc., using the same slug convention as [ADR-003](./adr-003-domain-events.md)). A delivery job signs (HMAC) and POSTs with retries. This is the generic mechanism through which *any* external tool — n8n, Zapier, a custom script, a partner's own backend — subscribes to what happens inside RetailPulse, without RetailPulse needing to know that tool exists.

## RetailPulse owns business logic; external tools orchestrate

This is the load-bearing distinction for every integration decision:

- **RetailPulse decides and executes** what a sale, a refund, a stock movement, a PO approval *means* and *does* to its own data — tax calculation, inventory deduction, ledger posting, workflow approval ([ADR-006](./adr-006-workflow-engine.md)). No external orchestrator is ever the source of truth for a RetailPulse business rule, and no integration should require an external system to be reachable for a core transaction to complete (see `fbr.failure_mode = queue`, Phase 8, as the existing precedent: external dependency failure degrades gracefully, it doesn't block the sale).
- **External tools (n8n, Zapier, a partner's iPaaS, a bespoke script) may sequence, filter, and fan out** RetailPulse's own outbound webhook events into whatever the business wants downstream — a Slack notification, a Google Sheet row, a CRM update. RetailPulse does not need to know or care what a subscriber does with an event once delivered.
- Consequently: **RetailPulse does not adopt n8n (or any external orchestrator) as internal infrastructure.** It is a valid *consumer* of RetailPulse's webhook registry that some businesses may run themselves; it is never where RetailPulse's own approval logic, tax logic, or posting logic lives — that would violate [ADR-004](./adr-004-layered-architecture.md)'s layering and make core business behavior depend on an external system's uptime and configuration.

## Provider adapters are swappable, not hardcoded

Payment gateways, fiscal reporting (FBR), and future accounting connectors all follow the same shape already established by `FbrReportingService` and the `payment_gateway_configs` table: an interface/service the core app calls, with the concrete provider selected by configuration (`system_settings` / per-branch config), not by an `if ($provider === 'stripe')` branch scattered through business logic. The Phase 15 doc's `FiscalProviderInterface` is the pattern to replicate for every new external system category — new provider, new adapter class, no change to the calling code's contract.

## E-commerce sync specifics (Shopify/WooCommerce/TikTok Shop)

Product and inventory sync is one-way-of-truth per field: RetailPulse is authoritative for inventory levels (pushed out), the external channel is authoritative for order creation (pulled in) — this avoids the classic double-sync conflict where both systems think they own the same number. Customer merge (Phase 25) resolves identity by verified contact match (email/phone), never by silently overwriting an existing RetailPulse customer record from channel data without a merge decision.

## WhatsApp and communications

WhatsApp (Cloud API), SMS (Twilio), and email (SendGrid/Mailgun) are outbound communication providers behind the same "swappable adapter selected by config" pattern as payments — a business chooses its provider in Settings; the calling code (invoice sharing, notifications) never hardcodes a specific vendor's SDK call inline.

# Consequences

- Adding a new external integration means: define the event slugs it needs (already exist per [ADR-003](./adr-003-domain-events.md) if the underlying business event exists), add a provider adapter behind an interface, and if inbound, add a signed webhook receiver that queues work. It does not mean adding branching logic to core services.
- A business can wire RetailPulse into n8n, Zapier, or a custom integration purely through the webhook registry and public API ([ADR-008](./adr-008-public-api.md)) — no core code change required, and no risk that an external tool's downtime affects a checkout or a payroll run.
- If a future requirement seems to need core business logic to live inside an external orchestrator, that is a signal the requirement has been mis-scoped as an integration when it is actually a Workflow Engine ([ADR-006](./adr-006-workflow-engine.md)) or core-service requirement — resolve the ambiguity before building, not after.
