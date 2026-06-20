# Phase 8 — Checkout, Payments & Invoicing

**SRS Reference:** §3.8, §3.18 (historical sales archive)
**Status:** ~95% Complete
**Depends on:** Phase 7 (POS Interface), Phase 17 (Shift & Register), Phase 4 (Products), Phase 5 (Inventory), Phase 1 (Roles & Permissions)
**Feeds into:** Phase 9 (Customer Accounts & Credit), Phase 11 (COGS posting), Phase 14 (Returns & Refunds), Phase 16 (Offline Sync)

---

## Objective

Own the complete **sale transaction lifecycle**: consume the Phase 7 cart handoff, collect payment via one or more configured methods, finalise the sale, post inventory movements, generate a compliant invoice, and optionally report to FBR (Pakistan Federal Board of Revenue) if that integration is enabled. Every behavioural, threshold, and integration setting is configuration-driven — nothing relevant to multi-tenancy or regional compliance is hardcoded.

---

## 1. Phase 7 Handoff Contract

When a cashier presses `F10` on the POS screen, Phase 7 transitions the cart to `status = 'completing'` and navigates to the Phase 8 checkout screen. Phase 8 consumes the cart server-side via a single bootstrap call.

### 1.1 Bootstrap Endpoint

```
GET /api/v1/checkout/{cart_id}
Permission: pos.access
```

Response — the checkout screen's initial state:

```json
{
  "cart_id": "uuid",
  "cashier_id": 42,
  "branch_id": 3,
  "items": [
    {
      "product_id": 101,
      "variant_id": 205,
      "sku": "SKU-XYZ",
      "name": "Product Name",
      "unit_price": "1200.00",
      "quantity": 2,
      "discount_type": "percent",
      "discount_value": "10.00",
      "line_total": "2160.00",
      "tax_rate": "0.16",
      "tax_amount": "345.60",
      "line_total_inc_tax": "2505.60"
    }
  ],
  "subtotal": "2160.00",
  "total_discount": "240.00",
  "tax_total": "345.60",
  "grand_total": "2505.60",
  "currency": "PKR",
  "notes": null,
  "customer": null,
  "config": {
    "tax_mode": "exclusive",
    "default_tax_rate": "0.16",
    "cash_change_enabled": true,
    "layaway_enabled": true,
    "split_tender_enabled": true,
    "fbr_enabled": false,
    "payment_methods": ["cash", "card", "mobile_wallet", "bank_transfer"],
    "invoice_templates": ["thermal_80mm", "a4"],
    "default_invoice_template": "a4",
    "invoice_number_prefix": "INV",
    "invoice_number_digits": 8,
    "max_layaway_balance_days": 30,
    "change_rounding_mode": "none"
  }
}
```

The `config` block is resolved at request time from the `system_settings` table (see §7). Phase 8 never has hardcoded behaviour — it branches on `config` values from this payload.

### 1.2 Cart → Sale State Ownership

The `pos_carts.status` state machine and `sales.status` state machine are deliberately separate:

| Event | `pos_carts.status` | `sales.status` |
|---|---|---|
| F10 pressed in Phase 7 | `completing` | — (not yet created) |
| `POST /checkout/{cart_id}/confirm` called | `completed` | `pending_payment` |
| All payments collected, sale finalised | — | `completed` |
| Cashier abandons checkout screen | `active` (restored) | — |
| Cashier voids from checkout screen | `voided` | `voided` |

Phase 8 owns the `sale` record creation. Phase 7 owns the `cart`. The seam is `cart_id` — Phase 8 reads it, creates the sale, and marks the cart completed atomically in a single database transaction.

---

## 2. Sale State Machine

All valid states and transitions are listed here. Developers must not introduce transitions outside this table.

```
draft ──────────────────────────────► voided
  │
  ▼
pending_payment ────────────────────► voided
  │                   │
  ▼                   ▼
partially_paid      completed ──────► refunded  (Phase 14)
  │
  ▼
completed
```

| Transition | Trigger | Notes |
|---|---|---|
| `draft → pending_payment` | `POST /checkout/{cart_id}/confirm` | Sale record created |
| `pending_payment → completed` | All `balance_due = 0` | Final payment applied |
| `pending_payment → partially_paid` | Layaway deposit recorded | `balance_due > 0` remains |
| `partially_paid → completed` | Remaining balance cleared | |
| `pending_payment → voided` | Cashier voids from checkout screen | Cart restored to `active` |
| `draft → voided` | Cart voided before confirm | |
| `completed → refunded` | Phase 14 refund flow | Out of scope Phase 8 |

Rules enforced at the API layer:

- A `completed` or `refunded` sale is immutable — no payment rows may be added or removed.
- A `voided` sale is soft-deleted — it persists in audit logs but is excluded from all KPI queries.
- The `voided` transition from `completed` is not allowed; use `refunded` (Phase 14).

---

## 3. Tax Calculation

Tax behaviour is fully driven by configuration — no rates, rules, or modes are hardcoded.

### 3.1 Configuration

| Setting key | Type | Default | Description |
|---|---|---|---|
| `tax.mode` | enum | `exclusive` | `inclusive` (tax included in `unit_price`) or `exclusive` (tax added on top) |
| `tax.default_rate` | decimal | `0.00` | Fallback rate (0–1) when no product/category override exists |
| `tax.per_item` | bool | `true` | When true, tax is computed per line item. When false, computed once on cart total |
| `tax.rounding` | enum | `half_up` | `half_up`, `half_even`, `truncate` |
| `tax.enabled` | bool | `true` | When false, tax column shows zero and no tax line appears on invoices |

### 3.2 Tax Rate Resolution (per line item)

Priority order — highest wins:

1. `product_variants.tax_rate` (variant-specific override)
2. `products.tax_rate` (product-level override)
3. `product_categories.tax_rate` (category-level override)
4. `system_settings.tax.default_rate` (global fallback)

### 3.3 Computation

For `tax.mode = 'exclusive'` and `tax.per_item = true`:

```
tax_amount  = ROUND(line_total × tax_rate, 2)
line_total_inc_tax = line_total + tax_amount
```

For `tax.mode = 'inclusive'`:

```
tax_amount  = ROUND(line_total − (line_total / (1 + tax_rate)), 2)
line_total_inc_tax = line_total
```

`tax_total` on the sale is `SUM(sale_items.tax_amount)` — never recomputed from grand totals.

### 3.4 Pakistan / FBR Compliance Note

When `fbr.enabled = true`, the tax rate applied to all taxable line items must equal the configured GST rate (`fbr.gst_rate`, default `0.16`). FBR integration detail is in §6.

---

## 4. Payment Processing

### 4.1 Supported Payment Methods

Supported methods are driven by `config.payment_methods` — an array populated from `system_settings`. Methods absent from the array do not appear in the UI.

| Method key | Description |
|---|---|
| `cash` | Physical cash; triggers change calculation |
| `card` | Card terminal; stub or live gateway (see §4.4) |
| `mobile_wallet` | JazzCash, EasyPaisa, or similar (see §4.4) |
| `bank_transfer` | Manual bank transfer; requires reference input |
| `credit` | Customer credit account (requires `customer_id`; Phase 9) |

### 4.2 Cash Change Calculation

When `payment_method = 'cash'` and `config.cash_change_enabled = true`:

- The cashier enters a `tendered_amount` (must be ≥ `balance_due`; API rejects less)
- `change_due = tendered_amount − balance_due`
- `change_due` is displayed prominently on the payment screen before confirming
- The `sale_payments` row stores `amount = balance_due` (not `tendered_amount`); `tendered_amount` is stored in `meta` (JSON)
- If `config.change_rounding_mode = 'nearest_5'` (or similar), change is rounded per that rule; the unrounded `tendered_amount` is preserved in `meta`

### 4.3 Split Tender

When `config.split_tender_enabled = true`:

- Multiple `sale_payments` rows may be attached to a single sale
- Each payment row reduces `balance_due`
- The sale moves to `completed` only when `balance_due = 0`
- UI shows running `balance_due` after each payment applied
- All payment methods may be mixed in a single split-tender sale

### 4.4 Payment Gateway Configuration

Gateways are configured per-branch via `payment_gateway_configs` (see §7.2). The gateway layer is abstracted — adding a new provider does not require code changes in the sale flow, only a new driver class.

**Stub mode vs live mode** — controlled per gateway by `gateway_configs.mode`:

| Mode | Behaviour |
|---|---|
| `stub` | Returns a hardcoded success response. No external API call. Marks `sale_payments.status = 'completed'`, `gateway_reference = 'STUB-{uuid}'`. Used for development/demo. |
| `live` | Makes a real API call to the configured provider. |
| `disabled` | Method is excluded from the payment methods list for that branch. |

**Required fields per gateway** (stored in `sale_payments.meta` JSON):

| Gateway | Required meta fields |
|---|---|
| `stripe` | `payment_intent_id`, `authorization_code`, `last4`, `card_brand` |
| `jazzcash` | `msisdn`, `transaction_reference`, `pp_response_code` |
| `easypaisa` | `msisdn`, `transaction_id`, `store_id` |
| `bank_transfer` | `bank_name`, `reference_number`, `deposited_at` |

**Gateway failure handling:**

1. Gateway returns an error → `sale_payments.status = 'failed'`; `gateway_response` stores the full error payload
2. Sale remains in `pending_payment` (or `partially_paid`) — it is not voided
3. Cashier is presented with the failure reason (user-friendly, not raw gateway error)
4. Cashier may retry the same method, switch method, or void the sale
5. A failed `sale_payments` row is retained for audit; retries create new rows

### 4.5 `sale_payments` Schema

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sale_id` | FK → sales | |
| `cashier_id` | FK → users | Who applied this payment |
| `method` | enum | `cash`, `card`, `mobile_wallet`, `bank_transfer`, `credit` |
| `amount` | decimal(12,2) | Actual payment applied (≤ balance_due at time of entry) |
| `status` | enum | `pending`, `completed`, `failed` |
| `gateway_reference` | varchar | nullable; populated by gateway on success |
| `meta` | json | Tendered amount, gateway-specific fields (see §4.4) |
| `gateway_response` | json | Full raw response payload (nullable) |
| `created_at` | timestamp | |

---

## 5. Layaway (Partial Payment / Deposit)

When `config.layaway_enabled = true`:

- A sale may be confirmed with partial payment; `balance_due > 0` moves it to `partially_paid`
- Each subsequent payment creates a new `sale_payments` row and reduces `balance_due`
- `config.max_layaway_balance_days` (default `30`) sets the maximum days a sale may remain in `partially_paid` before an overdue flag is set (surfaced in Phase 9 customer account view)
- Inventory is **not** deducted until the sale reaches `completed` (configurable — see §8 Inventory Deduction Timing)
- A minimum deposit amount may be enforced via `config.layaway_min_deposit_percent` (default `0`, meaning no minimum)

---

## 6. FBR / POS Integration (Configurable)

> This entire section is active only when `fbr.enabled = true`. When disabled, none of the FBR fields, invoice number formats, or API calls apply. The system falls back to the standard invoice number sequence defined in §9.

### 6.1 Feature Flag

```sql
-- system_settings
fbr.enabled             bool    default false
fbr.iris_endpoint       varchar nullable
fbr.pos_id              varchar nullable   -- assigned by FBR during registration
fbr.user_id             varchar nullable   -- FBR portal user ID
fbr.password_hash       varchar nullable   -- bcrypt hash of FBR API password
fbr.gst_rate            decimal default 0.16
fbr.failure_mode        enum    default 'queue'  -- 'block' or 'queue'
fbr.retry_max_attempts  int     default 3
fbr.retry_backoff_sec   int     default 60
```

### 6.2 FBR Invoice Number Format

When `fbr.enabled = true`, invoice numbers must conform to FBR's IRIS format:

```
{fbr.pos_id}-{YYYYMMDD}-{NNNNNNNN}
```

- `fbr.pos_id` — FBR-assigned POS identifier (configured in `system_settings`)
- `YYYYMMDD` — UTC sale date
- `NNNNNNNN` — 8-digit zero-padded daily sequence, reset to `00000001` at midnight UTC

The daily sequence is maintained in a `fbr_invoice_sequences` table with a `FOR UPDATE` row-level lock to prevent race conditions under concurrent checkout load.

### 6.3 FBR Reporting Payload

At sale completion, if `fbr.enabled = true`, Phase 8 calls the FBR IRIS reporting endpoint:

```json
{
  "InvoiceNumber": "POS123-20240115-00000001",
  "DateTime": "2024-01-15T14:30:00Z",
  "TotalBillAmount": 2505.60,
  "TotalTaxCharged": 345.60,
  "Discount": 240.00,
  "FurtherTax": 0,
  "InvoiceType": "SI",
  "PaymentMode": 1,
  "RefNo": null,
  "USIN": null,
  "BuyerName": null,
  "BuyerNTN": null,
  "BuyerCNIC": null,
  "BuyerPhoneNumber": null,
  "Items": [
    {
      "ItemCode": "SKU-XYZ",
      "ItemName": "Product Name",
      "Quantity": 2,
      "PCTCode": "",
      "TaxRate": 16,
      "SaleValue": 2160.00,
      "TaxCharged": 345.60,
      "Discount": 240.00,
      "FurtherTax": 0,
      "InvoiceValue": 2505.60
    }
  ]
}
```

### 6.4 Failure Handling

Controlled by `fbr.failure_mode`:

**`block` mode:**
- If the FBR API call fails (timeout, HTTP error, invalid response), the sale is NOT finalised
- Cashier sees: "FBR reporting failed. Please retry or contact support."
- Sale remains in `pending_payment`; no `sale_invoices` row is created

**`queue` mode (recommended for production):**
- Sale is finalised immediately; `sale_invoices` row is created with `fbr_status = 'pending'`
- A background queue job (`fbr_invoice_queue`) retries the FBR API call with exponential backoff
- After `fbr.retry_max_attempts` failures, `fbr_status = 'failed'` and an admin alert is raised
- Successful FBR response sets `fbr_status = 'submitted'` and stores `fbr_invoice_number` from IRIS response

### 6.5 FBR Invoice Statuses

| `fbr_status` | Meaning |
|---|---|
| `not_applicable` | `fbr.enabled = false` at time of sale |
| `pending` | Queued; not yet sent to IRIS |
| `submitted` | IRIS accepted; confirmation stored |
| `failed` | Max retries exhausted; admin action required |
| `blocked` | `failure_mode = 'block'` and API failed; sale not completed |

---

## 7. Configuration & Settings

All configurable values are stored in `system_settings` (key-value with type metadata) and scoped at the system or branch level. None are hardcoded in application code.

### 7.1 `system_settings`

| Key | Type | Default | Scope |
|---|---|---|---|
| `tax.enabled` | bool | `true` | System |
| `tax.mode` | enum | `exclusive` | System |
| `tax.default_rate` | decimal | `0.00` | System |
| `tax.per_item` | bool | `true` | System |
| `tax.rounding` | enum | `half_up` | System |
| `cash_change.enabled` | bool | `true` | System |
| `cash_change.rounding_mode` | enum | `none` | Branch |
| `split_tender.enabled` | bool | `true` | System |
| `layaway.enabled` | bool | `false` | System |
| `layaway.min_deposit_percent` | decimal | `0.00` | System |
| `layaway.max_balance_days` | int | `30` | System |
| `invoice.number_prefix` | varchar | `INV` | Branch |
| `invoice.number_digits` | int | `8` | System |
| `invoice.sequence_scope` | enum | `branch` | System |
| `invoice.default_template` | enum | `a4` | Branch |
| `invoice.share_methods` | json | `["email","link"]` | System |
| `fbr.enabled` | bool | `false` | System |
| `fbr.iris_endpoint` | varchar | null | System |
| `fbr.pos_id` | varchar | null | Branch |
| `fbr.user_id` | varchar | null | System |
| `fbr.password_hash` | varchar | null | System |
| `fbr.gst_rate` | decimal | `0.16` | System |
| `fbr.failure_mode` | enum | `queue` | System |
| `fbr.retry_max_attempts` | int | `3` | System |
| `fbr.retry_backoff_sec` | int | `60` | System |
| `payment_methods.enabled` | json | `["cash"]` | Branch |
| `inventory.deduct_on` | enum | `sale_completed` | System |

### 7.2 `payment_gateway_configs`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `branch_id` | FK → branches | nullable = system-wide |
| `gateway` | varchar | `stripe`, `jazzcash`, `easypaisa` |
| `mode` | enum | `stub`, `live`, `disabled` |
| `credentials` | json | Encrypted at rest; decrypted only in gateway driver |
| `priority` | int | Display order in payment method selector |
| `created_at` / `updated_at` | timestamps | |

---

## 8. Inventory Deduction Timing

Controlled by `system_settings.inventory.deduct_on`:

| Value | When stock deducts | Notes |
|---|---|---|
| `sale_completed` | When `sales.status → completed` | **Default.** Stock not affected during payment or layaway. |
| `payment_started` | When first payment row is created | Reserves stock during payment collection. |

**On payment failure (any mode):** No stock movement is reversed because no deduction has occurred unless `deduct_on = 'payment_started'` — in that case, a reversal movement is posted automatically.

**On layaway** (when `deduct_on = 'sale_completed'`): Stock remains unreduced until the final payment closes the sale. This is the safe default — stock is only committed when the sale is confirmed.

**On void from checkout screen:** If the cashier voids while on the payment screen (before any payment), the cart is restored to `active` status. No sale record exists, no inventory movement is posted.

All stock movements post to `inventory_movements` with:

- `source_type = 'sale'`
- `source_id = sale.id`
- `movement_type = 'outbound'`
- `branch_id` and `warehouse_id` from the originating cart

---

## 9. Invoice Number Format & Sequence

When `fbr.enabled = false`, the standard invoice number format applies:

```
{prefix}-{YYYYMMDD}-{NNNNNNN...}
```

- `prefix` — from `system_settings.invoice.number_prefix` (default `INV`)
- `YYYYMMDD` — local sale date in branch timezone
- `N...` — zero-padded daily sequence; digit count from `system_settings.invoice.number_digits` (default 8)

The sequence is scoped per branch (`invoice.sequence_scope = 'branch'`) or globally. A `sale_invoice_sequences` table holds the current counter with a row-level lock preventing race conditions. The `InvoiceNumberService` from Phase 7 is **reused** for this generation — Phase 8 does not implement a parallel sequence service. If FBR is enabled, this service is bypassed and the FBR format from §6.2 is used instead.

---

## 10. Customer Attachment

A sale may optionally be linked to a customer (`sales.customer_id`):

| Scenario | Behaviour |
|---|---|
| No customer attached | `customer_id = null`; sale proceeds normally |
| Customer selected at checkout | Customer lookup returns `customer_id`; stored on `sales` |
| Credit sale (`method = 'credit'`) | `customer_id` is **required**; API returns 422 if null |
| Customer credit limit enforcement | Phase 9 — not enforced in Phase 8 |

Customer lookup in Phase 8 is read-only — cashier selects an existing customer by name/phone search. Customer creation and credit account management belong to Phase 9.

---

## 11. Sale Void at Checkout (Reverse Handoff to Phase 7)

If the cashier presses "Back to POS" or voids the sale while on the checkout screen:

1. No payment rows have been created → `pos_carts.status` is restored to `active`; no sale record exists
2. Payment rows exist (partial split-tender in progress) → Cashier must confirm void; any `completed` payment rows must be manually reversed (offline card terminals) before void is permitted; `pending` rows are discarded
3. `sales.status → voided`; `pos_carts.status → voided`
4. Cashier is returned to the POS with a new empty cart slot

This reverse handoff requires `pos.void-cart` permission, consistent with Phase 7.

---

## 12. Invoice Generation & Sharing

### 12.1 Templates

| Template key | Paper size | Engine | Notes |
|---|---|---|---|
| `thermal_80mm` | 80mm thermal roll | DomPDF | Compact; no logo |
| `a4` | A4 portrait | DomPDF | Full invoice with logo, terms |

Active templates per branch are configured in `system_settings.invoice.default_template`. Additional templates may be added without code changes by placing a new Blade view in `resources/views/invoices/` and registering the key in `invoice_templates`.

### 12.2 Share Methods

Enabled share methods are configured in `system_settings.invoice.share_methods`:

| Method | Description |
|---|---|
| `email` | Send PDF to customer email (requires `customer_id` with email on record) |
| `link` | Generate a public token link (`/invoice/{public_token}`); token is UUID, no expiry by default |
| `whatsapp` | POST to WhatsApp Business API stub; `config.whatsapp_api_url` must be set |
| `print` | Trigger browser print dialog for thermal/A4 PDF |

### 12.3 `sale_invoices` Schema

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sale_id` | FK → sales | |
| `number` | varchar | Formatted invoice number (§9 or §6.2) |
| `template` | varchar | Template key used |
| `pdf_path` | varchar | Storage path; nullable until generated |
| `public_token` | uuid | For shareable link |
| `fbr_status` | enum | See §6.5; `not_applicable` when FBR disabled |
| `fbr_invoice_number` | varchar | IRIS-returned number; nullable |
| `emailed_at` | timestamp | nullable |
| `created_at` / `updated_at` | timestamps | |

---

## 13. Historical Sales Import (§3.18)

### 13.1 Purpose

Bulk-load past transaction data before go-live so that trend reports, customer purchase history, and KPI dashboards have historical context.

### 13.2 Behaviour

- Import records set `sales.is_historical = true`
- Historical sales do **not** post inventory movements (stock is as-is at go-live)
- Historical sales do **not** trigger FBR reporting regardless of `fbr.enabled`
- Historical sales do **not** generate new invoice PDFs (existing invoice reference stored in `sale_invoices.number` as-is)
- Dashboard KPIs exclude historical rows by default; a filter toggle exposes them

### 13.3 Import Validation

| Rule | Behaviour on failure |
|---|---|
| `branch_id` must exist | Row rejected; error row in import report |
| `cashier_id` optional for historical | Defaults to the importing user |
| `sale_date` must be in the past | Row rejected |
| Duplicate `invoice_number` within same branch | Row rejected; logged as duplicate |
| `is_historical` must be `true` | Enforced by importer; cannot be false on import |

Permission required: `sales.import-historical`

---

## 14. Data Model

### `sales`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `cart_id` | FK → pos_carts | nullable; null for imported historical sales |
| `branch_id` | FK → branches | |
| `warehouse_id` | FK → warehouses | nullable |
| `customer_id` | FK → customers | nullable |
| `cashier_id` | FK → users | |
| `status` | enum | See §2 state machine |
| `subtotal` | decimal(12,2) | Before tax and discount |
| `total_discount` | decimal(12,2) | |
| `tax_total` | decimal(12,2) | |
| `grand_total` | decimal(12,2) | Amount due |
| `balance_due` | decimal(12,2) | Decrements with each payment |
| `currency` | char(3) | Default `PKR`; from `system_settings.currency` |
| `tax_mode` | enum | Snapshot of `tax.mode` at time of sale |
| `notes` | text | nullable |
| `is_historical` | bool | Default `false` |
| `voided_at` | timestamp | nullable |
| `completed_at` | timestamp | nullable |
| `created_at` / `updated_at` | timestamps | |

### `sale_items`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sale_id` | FK → sales | |
| `product_id` | FK → products | |
| `variant_id` | FK → product_variants | nullable |
| `sku` | varchar | Snapshot |
| `name` | varchar | Snapshot |
| `unit_price` | decimal(12,2) | Snapshot |
| `quantity` | integer | |
| `discount_type` | enum | `flat`, `percent`, null |
| `discount_value` | decimal(12,2) | nullable |
| `line_total` | decimal(12,2) | Pre-tax net |
| `tax_rate` | decimal(6,4) | Snapshot of resolved rate |
| `tax_amount` | decimal(12,2) | Computed per §3.3 |
| `line_total_inc_tax` | decimal(12,2) | |

### `sale_payments`

See §4.5.

### `sale_invoices`

See §12.3.

### `fbr_invoice_sequences`

| Column | Type | Notes |
|---|---|---|
| `branch_id` | FK → branches | PK component |
| `date` | date | PK component; daily reset |
| `last_sequence` | int | Incremented with `FOR UPDATE` lock |

### `fbr_invoice_queue`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sale_invoice_id` | FK → sale_invoices | |
| `attempts` | int | Default 0 |
| `last_attempted_at` | timestamp | nullable |
| `next_attempt_at` | timestamp | nullable |
| `last_error` | text | nullable |
| `status` | enum | `pending`, `submitted`, `failed` |

---

## 15. Routes

### API

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| `GET` | `/api/v1/checkout/{cart_id}` | `pos.access` | Bootstrap checkout; returns cart + config |
| `POST` | `/api/v1/checkout/{cart_id}/confirm` | `pos.access` | Create sale record; transition cart → completed |
| `POST` | `/api/v1/sales/{id}/payments` | `pos.access` | Add payment row; updates balance_due |
| `POST` | `/api/v1/sales/{id}/void` | `pos.void-cart` | Void sale; restores cart if no payments |
| `GET` | `/api/v1/sales/{id}` | `sales.view` | Sale detail |
| `GET` | `/api/v1/sales/{id}/invoice` | `sales.view` | Invoice metadata |
| `POST` | `/api/v1/sales/{id}/invoice/pdf` | `sales.view` | Generate/regenerate PDF |
| `POST` | `/api/v1/sales/{id}/invoice/share` | `sales.view` | Send via configured share method |
| `GET` | `/invoice/{public_token}` | public | Public invoice view |
| `POST` | `/api/v1/sales/import-historical` | `sales.import-historical` | Bulk historical import |
| `GET` | `/api/v1/sales/export` | `sales.export` | CSV/XLSX export |

### Frontend

| Route | Middleware | Description |
|---|---|---|
| `/checkout/{cart_id}` | `auth`, `pos.access`, `cashier.pin` | Checkout screen |
| `/invoice/{public_token}` | none | Public shareable invoice |

---

## 16. Permissions

| Permission | Description |
|---|---|
| `pos.access` | Operate checkout screen; create sales and payments |
| `pos.void-cart` | Void a sale from the checkout screen |
| `sales.view` | View sale records and invoices |
| `sales.create` | (Implied by `pos.access`) |
| `sales.refund` | Void a completed sale — Phase 14 only |
| `sales.import-historical` | Run historical sales import |
| `sales.export` | Export sale records |
| `settings.manage` | Update `system_settings` and gateway configs |

---

## 17. Non-Functional Requirements

| Requirement | Target |
|---|---|
| Checkout bootstrap response | ≤ 300ms p95 |
| Payment row creation | ≤ 300ms p95 |
| Invoice PDF generation (20-line sale) | ≤ 3s |
| FBR API call timeout | Configurable; default 10s |
| Invoice sequence race condition | Zero duplicates under 100 concurrent checkouts |
| Historical import batch size | Up to 10,000 rows per job |
| Browser support | Chrome 110+, Edge 110+, Safari 16+ |

---

## 18. Acceptance Criteria

1. A $100 sale paid $60 card + $40 cash creates two `sale_payments` rows; `balance_due` reaches 0 and `sales.status` transitions to `completed`.

2. A layaway sale with a $30 deposit on a $100 total sets `balance_due = 70.00` and `status = 'partially_paid'`; a second payment of $70 clears the balance and moves status to `completed`.

3. A cash payment of $120 on a $95.60 balance shows `change_due = 24.40` on screen before confirmation; `sale_payments.amount = 95.60` and `meta.tendered_amount = 120.00`.

4. A card gateway failure leaves the sale in `pending_payment`, stores the failure in `sale_payments.gateway_response`, and presents a user-friendly error message — the sale is not voided.

5. PDF invoice generates and downloads in under 3s for a 20-line sale using the `a4` template.

6. Historical sale import does not change `inventory_movements` or `quantity_on_hand`; dashboard KPI cards exclude historical rows by default; enabling the filter includes them.

7. With `fbr.enabled = false`, the standard invoice number format `INV-YYYYMMDD-NNNNNNNN` is used and no FBR API calls are made.

8. With `fbr.enabled = true` and `fbr.failure_mode = 'queue'`, a sale completes immediately even if the FBR API is unreachable; `fbr_status = 'pending'` and a queue job is created for retry.

9. With `fbr.enabled = true` and `fbr.failure_mode = 'block'`, a sale cannot be completed while the FBR API is unreachable; the cashier sees a clear error message.

10. Two simultaneous checkouts on the same branch-date generate sequential, non-duplicate FBR invoice numbers with no gaps.

11. Voiding a sale from the checkout screen (before any payment) restores `pos_carts.status = 'active'` and returns the cashier to the POS; no `sale` record persists.

12. A `completed` sale cannot have additional payment rows added; the API returns `422` with a clear error.

13. A credit sale (`method = 'credit'`) fails with `422` if `customer_id` is null.

14. The sale state machine rejects any transition not listed in §2; attempts to transition `completed → pending_payment` return `422`.

15. Tax amount on a 2× $1,200 item at 16% exclusive tax computes to `sale_items.tax_amount = 345.60`; `tax_total = 345.60`; `grand_total = 2505.60`.

---

## SRS v4.0 Enhancements (§3.8)

### Extended Payment Methods

- Unified payment screen supports **Gift Card**, **Store Credit**, and **Loyalty Wallet** (cross-ref Phase 24 / Phase 9) in addition to cash, card, wallets, bank transfer.
- Partial gift card / store credit redemption with remainder on balance.

### COGS Trigger on Sale Complete

- On `sales.status → completed`, dispatch `SaleCompleted` event consumed by `CostService` (Phase 11) for real-time COGS + Inventory credit posting.
- Checkout does not implement cost logic — delegates to Phase 11 `inventory_cost_layers`.

### Acceptance Criteria (v4.0)

1. Sale with gift card + cash split tender creates two `sale_payments` rows with correct balances.
2. Completed sale triggers COGS journal entry in same DB transaction (when Phase 11 active).
