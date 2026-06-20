# Phase 25 — E-Commerce & Omni-Channel Integration

**SRS Reference:** §3.27
**Status:** Planned
**Depends on:** Phase 15 (API & Integrations — webhook infrastructure), Phase 23 (Module Config Engine — ecommerce module gate)
**Feeds into:** Phase 26 (Mobile — customer app shows unified order history), Phase 27 (BI — online sales in data marts)

---

## Objective
Unify online and in-store operations by syncing product catalogues and inventory levels with Shopify and WooCommerce, pulling online orders into the POS as carts, and merging online and in-store customer profiles — enabling true omni-channel retail.

---

## 1. Data Model

### integration_configs
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| branch_id | bigint FK nullable | Null = applies to all branches |
| provider | varchar(80) | `shopify`, `woocommerce`, `daraz`, `tiktok_shop` |
| credentials | text | Encrypted JSON (API key, secret, shop URL) |
| settings | json | Sync intervals, field mappings, enabled features |
| is_active | boolean | |
| last_sync_at | timestamp nullable | |
| created_at / updated_at | timestamps | |

### integration_sync_logs
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| integration_config_id | bigint FK | |
| sync_type | varchar(80) | `product_push`, `inventory_push`, `order_pull` |
| status | enum | `started`, `completed`, `failed` |
| records_processed | integer | |
| records_failed | integer | |
| error_summary | text nullable | |
| started_at | timestamp | |
| completed_at | timestamp nullable | |

---

## 2. Shopify Integration

### Product Sync (Push)
`ShopifyProductSyncJob` — pushes product variants from the local catalogue to Shopify.
- Creates or updates Shopify products/variants via Shopify Admin REST API.
- Maps local fields: `name` → Shopify `title`, `sku` → Shopify `variant.sku`, `price` → `variant.price`, `images` → Shopify product images.
- Stores `shopify_product_id` and `shopify_variant_id` in a `integration_product_mappings` pivot table.
- Runs nightly by default; can be triggered manually or on `ProductUpdated` event (if `settings.sync_on_save = true`).

### Inventory Level Push
`ShopifyInventoryPushJob` — triggered by `InventoryStockChanged` event.
- Looks up `shopify_variant_id` for the changed variant.
- Calls Shopify Inventory API to set `available` quantity to current `quantity_on_hand`.
- Batched: multiple variant changes within a 30-second window are grouped into a single API call.

### Order Pull
`ShopifyOrderPullJob` — polls Shopify every N minutes (configurable; default 5 min) for new orders with status `paid`.
- For each new order: find or create customer (match by email → phone → create new with `source = shopify`).
- Creates a `PosCart` with items mapped from the Shopify order line items.
- Cart tagged with `source = shopify`, `external_order_id = shopify_order_id`.
- Notification dispatched to branch manager: "New online order #SHOPIFY-1234 received."

### Webhook Receiver (Phase 15 endpoint)
- `POST /api/v1/webhooks/shopify/products` — product create/update/delete webhook.
- `POST /api/v1/webhooks/shopify/orders` — new order webhook (supplementary to polling; reduces latency).
- `POST /api/v1/webhooks/shopify/refunds` — refund webhook → auto-create return in POS.

---

## 3. WooCommerce Integration

Same architecture as Shopify; different API client.
- REST API v3 (`/wp-json/wc/v3/`).
- Auth via consumer key/secret (WooCommerce API keys).
- Product mapping: WooCommerce `product` → local `Product`; WooCommerce `variation` → `ProductVariant`.
- Order pull: polls `/orders?status=processing&after={last_sync_at}`.

---

## 4. Daraz / TikTok Shop (Stubs)

- `DarazProvider` and `TikTokShopProvider` implement `EcommerceProviderInterface` with the same methods as Shopify/WooCommerce providers.
- Method bodies log the intent and return stubbed success responses.
- Credentials form in Integration Settings shows "Coming soon" badge when provider is a stub.

---

## 5. Unified Customer Profile Merge

`CustomerMergeService::matchOrCreate(array $externalData, string $source): Customer`

Match priority:
1. Exact email match on `customers.email`.
2. Phone number match on `customers.phone`.
3. If no match: create new customer with `source` field set.

On merge: loyalty points, wallet balance, and order history are unified under a single customer record. The `source` column tracks where the customer was first acquired.

---

## 6. integration_product_mappings (Helper Table)

```sql
CREATE TABLE integration_product_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    integration_config_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    external_product_id VARCHAR(255) NOT NULL,
    external_variant_id VARCHAR(255) NOT NULL,
    last_synced_at TIMESTAMP NULL,
    FOREIGN KEY (integration_config_id) REFERENCES integration_configs(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id),
    UNIQUE KEY uq_mapping (integration_config_id, product_variant_id)
);
```

---

## 7. Admin UI — Settings → Integrations

- **Integrations list:** Provider cards (Shopify, WooCommerce, Daraz, TikTok Shop) with status badge.
- **Connect:** Form to enter API credentials; "Test Connection" button validates credentials.
- **Sync Settings:** Configure sync interval, field mappings, enabled sync types (product/inventory/orders).
- **Sync Log:** Table of recent sync runs with status, record counts, and error summary.
- **Manual Trigger:** "Sync Products Now", "Sync Inventory Now", "Pull Orders Now" buttons.

---

## 8. API Endpoints

| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| GET | /api/v1/integrations | integrations.view | List integration configs |
| POST | /api/v1/integrations | integrations.manage | Create integration |
| PUT | /api/v1/integrations/{id} | integrations.manage | Update credentials/settings |
| POST | /api/v1/integrations/{id}/test | integrations.manage | Test connection |
| POST | /api/v1/integrations/{id}/sync/products | integrations.manage | Trigger product sync |
| POST | /api/v1/integrations/{id}/sync/inventory | integrations.manage | Trigger inventory push |
| POST | /api/v1/integrations/{id}/sync/orders | integrations.manage | Trigger order pull |
| GET | /api/v1/integrations/{id}/logs | integrations.view | Sync log |

---

## 9. Services & Classes

- `EcommerceProviderInterface` — contract: `syncProducts`, `pushInventory`, `pullOrders`, `testConnection`.
- `ShopifyProvider`, `WooCommerceProvider`, `DarazProvider` (stub), `TikTokShopProvider` (stub).
- `ShopifyProductSyncJob`, `ShopifyInventoryPushJob`, `ShopifyOrderPullJob`.
- `WooCommerceProductSyncJob`, `WooCommerceOrderPullJob`.
- `CustomerMergeService` — unified customer matching.
- `IntegrationSyncLogger` — writes `integration_sync_logs` entries.
- `InventoryStockChangedListener` → dispatches `ShopifyInventoryPushJob` when stock changes.
