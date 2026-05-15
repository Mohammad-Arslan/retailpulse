# Phase 7 — Point of Sale (POS) Interface

**SRS Reference:** §3.7, §4.2 (offline foundation)  
**Status:** Planned  
**Depends on:** Phase 4, Phase 5, Phase 1 (Cashier role)

---

## Objective

**Speed-first POS** SPA: product search, multi-cart, keyboard navigation, real-time stock validation.

## Features

- Dedicated POS layout (fullscreen, touch-friendly)
- Product search: debounced API from first keystroke; barcode scan input
- Multi-cart: hold/suspend carts with visual status indicator
- Cart line items: qty, discount, notes; server validates stock on add
- WebSocket warnings: `inventory.stock.changed`, credit limit (Phase 9 hook)
- Cashier PIN login (custom guard or secondary auth alongside Breeze session) — SRS §3.1
- IndexedDB `offline_sales` queue + service worker skeleton (full sync Phase 16)
- Permissions: `pos.access`, `pos.discount`, `pos.suspend-cart`

## Routes

- `/pos` — Cashier-only middleware
- API: `POST /api/v1/pos/carts`, `POST .../items`, etc.

## Acceptance Criteria

1. Cashier completes keyboard-only sale flow (add → pay navigates to Phase 8).
2. Suspended cart restores with correct lines.
3. Adding out-of-stock item shows inline warning without page reload.
