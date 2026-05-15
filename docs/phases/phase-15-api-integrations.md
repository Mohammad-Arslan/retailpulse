# Phase 15 — API, Webhooks & Third-Party Integrations

**SRS Reference:** §4.5, §6  
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

## Acceptance Criteria

1. Third-party token with `sales:read` can list sales but not create users.
2. Registered webhook receives signed POST on sale complete.
3. Stripe test payment succeeds in sandbox from POS.
