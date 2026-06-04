# Phase 15 — API, Webhooks & Third-Party Integrations

**SRS Reference:** §4.5, §6, §3.18 (API import/export)  
**Status:** Planned  
**Depends on:** Phase 1 (Sanctum), core modules through Phase 14

---

## Objective

**Public API v1**, admin token management, **webhooks**, and payment/communication provider integrations.

## Features

- All resources exposed under `/api/v1/` with Sanctum token abilities
- OpenAPI/Swagger documentation (e.g. Scramble or L5-Swagger)
- Admin UI: create/revoke API tokens with scoped abilities
- Webhook registry: URL, secret, subscribed events (`order.created`, `refund.processed`)
- Webhook delivery job with retries and signing (HMAC)
- Integrations:
  - **Payments:** Stripe, PayPal, JazzCash, EasyPaisa (gateway adapters)
  - **Comms:** Twilio SMS, SendGrid/Mailgun email, WhatsApp Cloud API
  - **Accounting:** Xero/QuickBooks connector stubs
- Rate limiting per token and per IP
- **Import/export API (§3.18):** `/api/v1/{resource}/import` and `/export` for products, customers, suppliers (token abilities e.g. `products:import`)

## Acceptance Criteria

1. Third-party token with `sales:read` can list sales but not create users.
2. Registered webhook receives signed POST on sale complete.
3. Stripe test payment succeeds in sandbox from POS.

---

## Phase Enhancements (SRS v3.0)

### Fiscal Provider Abstraction Endpoints
- `GET /api/v1/fiscal/providers` — list available fiscal providers and their status (enabled/disabled per branch).
- `GET /api/v1/fiscal/invoices/{id}` — retrieve fiscal invoice record including submission status, request payload, and response payload.
- `POST /api/v1/fiscal/invoices/{id}/retry` — manually trigger a retry for a queued fiscal submission.
- These endpoints use the `FiscalProviderInterface` abstraction; swapping providers requires only a config change, not API contract changes.

### E-Commerce Webhook Stubs
- `POST /api/v1/webhooks/shopify/products` — receives Shopify product create/update webhooks; validates HMAC signature; queues a `ShopifyProductSyncJob`.
- `POST /api/v1/webhooks/shopify/orders` — receives new order webhooks; queues a `ShopifyOrderPullJob` that creates a POS cart.
- `POST /api/v1/webhooks/woocommerce/orders` — same pattern for WooCommerce (JWT-based auth instead of HMAC).
- All webhook receivers respond `200 OK` immediately and process asynchronously to meet platform timeout requirements.

### Hardware Device Registration API
- `GET /api/v1/devices` — list registered hardware devices for the authenticated branch.
- `POST /api/v1/devices` — register a new printer or peripheral device with config JSON.
- `PUT /api/v1/devices/{id}` — update device config (e.g., change IP address).
- `POST /api/v1/devices/{id}/test-print` — dispatch a test print job to the specified printer.
- `DELETE /api/v1/devices/{id}` — deregister a device.
- Used by the Hardware Settings UI (Phase 21) and callable by third-party integrators.
