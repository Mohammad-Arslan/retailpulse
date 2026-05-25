# Phase 7 — Point of Sale (POS) Interface

**SRS Reference:** §3.7, §4.2 (offline foundation)  
**Status:** Planned  
**Depends on:** Phase 4, Phase 5, Phase 1 (Cashier role)  
**Feeds into:** Phase 8 (Payment Processing), Phase 16 (Offline Sync)

---

## Objective

**Speed-first POS** SPA: product search, multi-cart, keyboard navigation, and real-time stock validation. The interface must support a full keyboard-only sale flow with zero required mouse interaction for the core cashier path.

---

## Features

### 1. Dedicated POS Layout

- Fullscreen single-page application, separate from the main admin shell
- Touch-friendly tap targets (minimum 44×44px hit areas per WCAG 2.5.5)
- Two-panel layout: left panel for product search/catalog, right panel for active cart
- Persistent header: cashier name, session timer, active cart count, connectivity indicator

---

### 2. Product Search

- Debounced API call triggered from the first keystroke (300ms debounce)
- Search matches against product name, SKU, and barcode fields
- Results display: product name, SKU, unit price, and available stock quantity
- Keyboard navigation: `↑`/`↓` to move through results, `Enter` to add to cart
- **Barcode Scanner Input:**
  - Supported formats: EAN-13, EAN-8, Code 128, QR Code
  - Scanner input distinguished from keyboard typing via suffix character (`\n` or `\t`) or a timing threshold of ≤50ms between characters
  - If scanned barcode yields no match: inline error toast — "Product not found for barcode `{code}`" — with no page reload
  - If multiple SKUs match a barcode: disambiguation modal listing matches

---

### 3. Multi-Cart (Hold/Suspend)

- Cashier may hold up to **5 carts** simultaneously; attempting a 6th prompts the cashier to complete or void an existing cart
- Each cart shows a visual status chip: `Active`, `Suspended`, `Completing`
- Suspended carts are persisted in the database (`pos_carts` table — see §Data Model) and survive browser refresh or re-login
- Resuming a suspended cart restores all line items, quantities, discounts, and notes exactly
- Keyboard shortcut: `Ctrl+H` to suspend active cart, `Ctrl+1`–`Ctrl+5` to switch between open carts

---

### 4. Cart Line Items

Each line item contains:

| Field       | Type     | Notes                                              |
|-------------|----------|----------------------------------------------------|
| `product_id`| FK       | Reference to product catalog                       |
| `sku`       | string   | Snapshot at time of add                            |
| `name`      | string   | Snapshot at time of add                            |
| `unit_price`| decimal  | Snapshot at time of add                            |
| `quantity`  | integer  | Min 1; validated against stock on every change     |
| `discount`  | decimal  | See §Discount Rules                                |
| `notes`     | text     | Optional cashier note per line                     |
| `line_total`| computed | `(unit_price − discount) × quantity`               |

- **Stock validation** fires on: initial add, quantity increase, and cart resume
- Adding an item with zero available stock shows an inline warning row — "Out of stock" — without page reload; item is blocked from being added
- Quantity increase beyond available stock shows a warning — "Only `{n}` units available" — and caps the quantity at the available amount unless a manager override is granted (see §Permissions)

---

### 5. Stock Validation Edge Cases

| Scenario | Behaviour |
|---|---|
| Item added, stock later drops to zero | WebSocket `inventory.stock.changed` event triggers inline warning on the line item; cashier prompted to confirm or remove |
| Stock drops below cart quantity mid-session | Line highlighted in amber with inline message; cashier must acknowledge before proceeding to payment |
| Cashier has `pos.override-stock` permission | Override button appears on warning; requires manager PIN confirmation |
| Item fully out of stock at time of add | Blocked entirely; no override available without `pos.override-stock` |

---

### 6. WebSocket Events

| Event | Trigger | Action |
|---|---|---|
| `inventory.stock.changed` | Stock level changes for any product in any open cart | Re-validate affected line items; display inline warning if quantity now exceeds available stock |
| `customer.credit.limit` | Phase 9 hook — customer credit threshold breached | Show non-blocking warning banner on cart header (future phase; socket channel subscribed here) |

---

### 7. Cashier PIN Login

A secondary authentication layer on top of the active Breeze session, scoped to POS access.

**PIN Specification:**
- Numeric only, exactly 6 digits
- Stored as bcrypt hash (cost factor 10) in `users.pos_pin_hash`; never stored in plaintext
- POS session is independent of the browser session — PIN re-entry required after **30 minutes of inactivity** on the POS screen, or on any cart resume if the session has expired

**Lockout Policy:**
- 5 consecutive failed PIN attempts triggers a **15-minute lockout** for that user on the POS
- Lockout state stored server-side (`pos_pin_lockouts` table); not bypassable by browser refresh
- Locked-out cashier sees: "Too many failed attempts. Try again in `{N}` minutes." with a countdown
- A user with `pos.admin` permission may reset a lockout immediately

**PIN Management:**
- PINs set and changed via the main admin user profile screen (not within POS)
- PIN reset by admin generates a one-time setup link sent via internal notification

---

### 8. Discount Rules

| Rule | Detail |
|---|---|
| Type | Flat amount (`PKR`) or percentage (`%`), selectable per line item |
| Scope | Per line item only; no cart-level blanket discount in this phase |
| Permission | Requires `pos.discount` permission |
| Maximum | Flat: cannot exceed line `unit_price × quantity`; Percentage: max **30%** without manager approval |
| Manager approval | Discounts above 30% require a second cashier with `pos.approve-discount` to enter their PIN |
| Enforcement | Validated both client-side (UX) and server-side (API rejects out-of-range values) |
| Audit | All discounts logged with cashier ID, timestamp, and approval chain in `pos_discount_logs` |

---

### 9. Keyboard Navigation Map

Full keyboard-only sale flow — no mouse required:

| Key | Action |
|---|---|
| `F2` | Focus product search input |
| `↑` / `↓` | Navigate search results |
| `Enter` | Add focused product to active cart |
| `Esc` | Clear search / close modal |
| `F3` | Focus quantity field of last added line item |
| `F4` | Focus discount field of selected line item |
| `Delete` | Remove selected line item (confirmation prompt) |
| `Ctrl+H` | Suspend active cart |
| `Ctrl+1`–`5` | Switch to cart slot 1–5 |
| `Ctrl+V` | Void active cart (requires confirmation) |
| `F10` | Proceed to payment (Phase 8 handoff) |
| `Ctrl+Z` | Undo last line item add |

---

### 10. Phase 8 Payment Handoff Payload

When the cashier triggers "Proceed to Payment" (`F10`), the POS navigates to the Phase 8 payment screen passing the following payload (via server-side cart state, not URL params):

```json
{
  "cart_id": "uuid",
  "cashier_id": "integer",
  "branch_id": "integer",
  "items": [
    {
      "product_id": "integer",
      "sku": "string",
      "name": "string",
      "unit_price": "decimal",
      "quantity": "integer",
      "discount_type": "flat|percent",
      "discount_value": "decimal",
      "line_total": "decimal"
    }
  ],
  "subtotal": "decimal",
  "total_discount": "decimal",
  "grand_total": "decimal",
  "currency": "PKR",
  "notes": "string|null"
}
```

Phase 8 owns all payment method selection, change calculation, and receipt generation. The POS does not duplicate this logic.

---

### 11. Cart Void

- Any cart (active or suspended) may be voided by a cashier with `pos.void-cart` permission
- Voiding requires a confirmation dialog: "Void this cart? This cannot be undone."
- Voided carts are soft-deleted (`pos_carts.status = 'voided'`) and remain in audit logs
- Cashier without `pos.void-cart` may only void their own active cart; voids of other cashiers' suspended carts require a manager

---

### 12. Offline Support (Foundation)

- IndexedDB store: `offline_sales` queue — saves pending cart actions when connectivity is lost
- Service worker registered on POS load; fetch interception scoped to `/api/v1/pos/*`
- When offline: connectivity indicator turns amber; cashier may continue adding items from a locally cached product catalog snapshot (refreshed on each online POS session start)
- Sync strategy and conflict resolution deferred to Phase 16; this phase only establishes the queue and service worker skeleton
- **Conflict assumption (Phase 16):** server stock is authoritative; offline sales that exceed stock on sync will be flagged for manual review, not auto-rejected

---

## Data Model

### `pos_carts`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `cashier_id` | FK → users | |
| `branch_id` | FK → branches | |
| `status` | enum | `active`, `suspended`, `completing`, `completed`, `voided` |
| `slot` | tinyint | 1–5; unique per cashier per active session |
| `notes` | text | nullable |
| `suspended_at` | timestamp | nullable |
| `completed_at` | timestamp | nullable |
| `voided_at` | timestamp | nullable |
| `created_at` / `updated_at` | timestamps | |

### `pos_cart_items`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key |
| `cart_id` | FK → pos_carts | |
| `product_id` | FK → products | |
| `sku` | varchar | Snapshot |
| `name` | varchar | Snapshot |
| `unit_price` | decimal(10,2) | Snapshot |
| `quantity` | integer | |
| `discount_type` | enum | `flat`, `percent`, null |
| `discount_value` | decimal(10,2) | nullable |
| `line_total` | decimal(10,2) | Computed and stored |
| `notes` | text | nullable |

### `pos_pin_lockouts`

| Column | Type | Notes |
|---|---|---|
| `user_id` | FK → users | |
| `failed_attempts` | tinyint | Reset on successful PIN |
| `locked_until` | timestamp | nullable |

---

## Routes

### Frontend

| Route | Middleware | Description |
|---|---|---|
| `/pos` | `auth`, `pos.access`, `cashier.pin` | Main POS SPA entry point |

### API

| Method | Endpoint | Permission | Description |
|---|---|---|---|
| `POST` | `/api/v1/pos/carts` | `pos.access` | Create new cart |
| `GET` | `/api/v1/pos/carts/{id}` | `pos.access` | Load cart with items |
| `PATCH` | `/api/v1/pos/carts/{id}/suspend` | `pos.suspend-cart` | Suspend cart |
| `PATCH` | `/api/v1/pos/carts/{id}/void` | `pos.void-cart` | Void cart |
| `POST` | `/api/v1/pos/carts/{id}/items` | `pos.access` | Add item (validates stock) |
| `PATCH` | `/api/v1/pos/carts/{id}/items/{itemId}` | `pos.access` | Update qty/discount/notes |
| `DELETE` | `/api/v1/pos/carts/{id}/items/{itemId}` | `pos.access` | Remove line item |
| `POST` | `/api/v1/pos/carts/{id}/checkout` | `pos.access` | Hand off to Phase 8 |
| `POST` | `/api/v1/pos/pin/verify` | `auth` | Verify cashier PIN |
| `POST` | `/api/v1/pos/pin/reset` | `pos.admin` | Admin PIN reset |
| `GET` | `/api/v1/pos/products/search` | `pos.access` | Debounced product search |

---

## Permissions

| Permission | Description |
|---|---|
| `pos.access` | Enter and operate the POS screen |
| `pos.discount` | Apply discounts up to 30% on line items |
| `pos.approve-discount` | Approve discounts above 30% |
| `pos.suspend-cart` | Suspend and resume carts |
| `pos.void-cart` | Void any cart |
| `pos.override-stock` | Override out-of-stock warning (with manager PIN) |
| `pos.admin` | Reset PIN lockouts; manage POS sessions |

---

## Non-Functional Requirements

| Requirement | Target |
|---|---|
| Product search API response | ≤ 200ms p95 under normal load |
| Cart item add (with stock validation) | ≤ 300ms p95 |
| PIN verification | ≤ 150ms p95 |
| Concurrent carts per cashier | Max 5 |
| Offline product catalog cache | Refreshed on each POS session start; max 24hrs stale |
| Accessibility | WCAG 2.1 AA; full keyboard operability |
| Browser support | Chrome 110+, Edge 110+, Safari 16+ |
| Receipt printing | **Out of scope for Phase 7** — deferred to Phase 8 |

---

## Acceptance Criteria

1. Cashier completes a full keyboard-only sale flow — product search → add items → proceed to payment — without touching the mouse; `F10` triggers Phase 8 navigation with the correct payload.
2. A suspended cart restores all line items, quantities, discounts, and notes correctly after browser refresh or re-login.
3. Adding an item that is out of stock shows an inline warning row without a page reload; the item is not added to the cart.
4. If stock drops to zero for an item already in the cart, a WebSocket-triggered inline warning appears on the affected line; cashier must acknowledge before proceeding.
5. Six consecutive PIN failures (across 5 + 1 lockout trigger) lock the cashier out for 15 minutes; lockout persists across browser refresh.
6. A discount above 30% is blocked at the API level and requires a second cashier with `pos.approve-discount` to enter their PIN before it is applied.
7. Attempting to open a 6th simultaneous cart surfaces a prompt to complete or void an existing cart; no 6th slot is created.
8. Barcode scan input (EAN-13/128) correctly resolves to the matching product and adds it to the cart within 300ms.
9. Offline connectivity indicator appears when the browser loses network; previously cached product search results remain usable; queued actions are stored in IndexedDB `offline_sales`.
