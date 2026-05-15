# Phase 14 — Notifications, Returns & Tax Engine

**SRS Reference:** §3.15, §3.16, §3.17  
**Status:** Planned  
**Depends on:** Phase 8, Phase 6 (broadcasts)

---

## Objective

**Notification preferences**, **return/refund workflows**, and **composite tax** configuration.

## Database (key tables)

- `notification_preferences` — user_id, event, channels (email, push, database)
- `notifications` (Laravel), `system_broadcasts`
- `returns`, `return_items`, `refund_payments`
- `tax_rates`, `tax_groups`, `tax_group_rates`
- Product/customer: `tax_inclusive` flag

## Features

- Per-user notification prefs; admin broadcast to branch POS terminals
- Return policy: window days, manager PIN if refund > $X
- Multi-mode return: store credit, original payment, exchange
- Tax groups applied as single line, reported separately
- Inclusive/exclusive pricing at product and customer group level
- Permissions: `returns.*`, `returns.approve`, `tax.*`, `notifications.broadcast`

## Acceptance Criteria

1. Return outside 30-day window blocked unless override permission.
2. Composite tax on product shows correct breakdown on invoice.
3. Low-stock email sent only to users who opted in.
