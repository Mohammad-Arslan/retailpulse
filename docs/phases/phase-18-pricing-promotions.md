# Phase 18 — Advanced Pricing & Promotions Engine

**SRS Reference:** §3.21
**Status:** Planned
**Depends on:** Phase 4 (Products & Variants), Phase 9 (Customer Groups)
**Feeds into:** Phase 19 (Restaurant — service charge uses price engine), Phase 24 (Gift Cards — coupon redemption path)

---

## Objective
Replace the simple per-branch price override with a full layered pricing and promotions engine. Support multiple price lists, scheduled pricing, BOGO/bundle/cart/category promotions, and a coupon system — all resolved in a single `PriceResolutionService` call at cart-item creation.

---

## 1. Data Model

### price_lists
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| name | varchar(100) | "Wholesale", "VIP Members" |
| type | enum | `retail`, `wholesale`, `vip`, `branch`, `custom` |
| branch_id | bigint FK nullable | Scope to branch (null = global) |
| customer_group_id | bigint FK nullable | Auto-applies when customer group matches |
| is_default | boolean | The fallback for unmatched carts |
| status | enum | `active`, `inactive` |

### price_list_items
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| price_list_id | bigint FK | |
| product_variant_id | bigint FK | |
| price | decimal(12,2) | Override price |
| valid_from | date nullable | Start of scheduled window |
| valid_to | date nullable | End of scheduled window |

### promotions
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| name | varchar(150) | |
| type | enum | `bogo`, `bundle`, `cart_discount`, `category_discount` |
| conditions | json | Trigger rules (min qty, min value, customer_group, days, time range) |
| actions | json | Effect (discount_type, discount_value, free_item_variant_id) |
| stacking_mode | enum | `exclusive`, `stackable` |
| priority | integer | Tie-break for exclusive promotions |
| valid_from | date nullable | |
| valid_to | date nullable | |
| usage_limit | integer nullable | Total uses allowed (null = unlimited) |
| usage_count | integer | Incremented atomically |
| branch_id | bigint FK nullable | Null = all branches |
| status | enum | `active`, `inactive`, `scheduled` |

### coupons
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| code | varchar(50) unique | |
| promotion_id | bigint FK | |
| max_uses | integer nullable | null = unlimited |
| uses_count | integer | |
| per_customer_limit | integer | Default 1 |
| expires_at | timestamp nullable | |
| status | enum | `active`, `inactive`, `expired` |

### coupon_usages
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| coupon_id | bigint FK | |
| sale_id | bigint FK | |
| customer_id | bigint FK nullable | |
| used_at | timestamp | |

---

## 2. Price Resolution Order

`PriceResolutionService::resolve(ProductVariant $variant, Cart $cart): Money`

Resolution chain (first match wins):
1. Active scheduled price list item for the variant (date-range matched)
2. Customer group price list item (if customer attached to cart and has a group with a price list)
3. Branch price list item (if branch has a default price list)
4. `branch_product_prices` override (Phase 4 legacy, kept for backwards compat)
5. `product_variants.price` (base price)

---

## 3. Promotion Engine

`PromotionEngine::evaluate(Cart $cart): PromotionResult`

- Loads all `active` promotions applicable to the cart's branch and date/time.
- Evaluates each promotion's `conditions` JSON against the cart.
- Collects all matching promotions.
- Applies stacking rules: exclusive promotions → keep only the highest-priority/highest-discount one; stackable promotions → apply all.
- Returns `PromotionResult` with: `applied_promotions[]`, `line_discounts[]`, `cart_discount`, `free_items[]`.

### Condition Keys (conditions JSON)
```json
{
  "min_cart_value": 1000,
  "min_quantity": 2,
  "customer_group_ids": [1, 2],
  "product_variant_ids": [5, 10],
  "category_ids": [3],
  "days_of_week": ["monday", "friday"],
  "time_from": "12:00",
  "time_to": "15:00"
}
```

### Action Keys (actions JSON)
```json
{
  "discount_type": "percent",
  "discount_value": 10,
  "target": "cart",
  "free_item_variant_id": null,
  "free_item_qty": 0
}
```

---

## 4. Coupon Redemption Flow

1. Cashier enters coupon code in POS checkout screen.
2. `CouponService::validate($code, $cart)` checks: active, not expired, uses < max_uses, customer per-customer limit.
3. On success, linked promotion is applied to the cart via `PromotionEngine`.
4. On sale completion, `coupon_usages` record created; `coupons.uses_count` incremented atomically (`increment()` with a DB transaction).
5. If sale is voided, coupon usage is reversed.

---

## 5. Scheduled Pricing Jobs

- `RefreshPriceListStatusJob` — runs every 15 minutes; activates/deactivates promotions and price list items based on `valid_from`/`valid_to`; flushes the product price cache.
- `ExpireCouponsJob` — daily job; sets `status = expired` on coupons past `expires_at`.

---

## 6. Admin UI

- **Pricing → Price Lists:** CRUD; assign to customer groups or branches; manage price list items (import via shared import framework).
- **Pricing → Promotions:** CRUD with condition/action builder (form-based JSON editor); preview active promotions.
- **Pricing → Coupons:** Create single or bulk-generate coupon codes for a promotion; view usage count.

---

## 7. API Endpoints

| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| GET | /api/v1/pos/price-resolve | pos.access | Resolve price for a variant in cart context |
| POST | /api/v1/pos/promotions/evaluate | pos.access | Evaluate applicable promotions for a cart |
| POST | /api/v1/pos/coupons/validate | pos.access | Validate a coupon code |
| GET | /admin/pricing/price-lists | pricing.view | |
| POST | /admin/pricing/price-lists | pricing.manage | |
| GET | /admin/pricing/promotions | pricing.view | |
| POST | /admin/pricing/promotions | pricing.manage | |
| GET | /admin/pricing/coupons | pricing.view | |
| POST | /admin/pricing/coupons/bulk-generate | pricing.manage | |

---

## 8. Services & Classes

- `PriceResolutionService` — resolves final unit price for a variant in cart context.
- `PromotionEngine` — evaluates and applies matching promotions to a cart.
- `CouponService` — validate, apply, and reverse coupon usages.
- `PricingCacheService` — caches resolved prices with cache tag `pricing:{variant_id}`.
- `RefreshPriceListStatusJob` — scheduled activation/deactivation.
- `ExpireCouponsJob` — daily coupon expiry.
