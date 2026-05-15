# Phase 9 — Customer Relationship & Loyalty Management

**SRS Reference:** §3.9  
**Status:** Planned  
**Depends on:** Phase 8

---

## Objective

**360° customer profiles**, tiered loyalty, wallets, and store credit integrated with POS/checkout.

## Database (key tables)

- `customers` — name, email, phone, credit_limit, loyalty_tier_id, `tenant_id`
- `loyalty_tiers` — name, multiplier, min_points, auto_upgrade rules
- `loyalty_points` — customer_id, points, type (earn/redeem), sale_id
- `customer_wallets` — balance
- `wallet_transactions` — amount, type, reference

## Features

- Customer CRUD + search in POS
- Profile: transaction history, ATV, preferred payment, tier, wallet, credit balance
- Point rules engine: earn on sale complete; tier multiplier
- Auto tier upgrade/downgrade job
- Wallet top-up, pay-with-wallet at checkout
- Credit limit check on POS (WebSocket warning)
- Permissions: `customers.*`, `customers.view-credit`

## Acceptance Criteria

1. Gold tier customer earns 1.5× points on completed sale.
2. Wallet payment reduces balance and records transaction.
3. Credit sale blocked when over limit.
