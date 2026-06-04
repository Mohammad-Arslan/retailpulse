# Phase 19 — Restaurant Core

**SRS Reference:** §3.19
**Status:** Planned
**Depends on:** Phase 7 (POS Interface — order flow), Phase 17 (Shifts — shift must be open before orders)
**Feeds into:** Phase 20 (Restaurant Advanced — waiter app, KDS, split billing), Phase 22 (Recipe & Ingredients — raw material deduction)

---

## Objective
Enable the core restaurant workflow: floor/table management, dine-in order creation, KOT generation with kitchen-station routing, order lifecycle tracking, service charge, and the three additional order types (takeaway, delivery, drive-thru). This phase is the minimum viable restaurant build that a coffee shop or restaurant can go live on.

---

## 1. Module Gate

All restaurant features are behind the `restaurant` module flag. If `tenant_modules.restaurant` is disabled, all routes in this phase return 404 and no sidebar items appear. Enable the module in Settings → Modules.

---

## 2. Data Model

### floors
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| branch_id | bigint FK | |
| name | varchar(100) | "Ground Floor", "Rooftop" |
| layout | json nullable | Future: drag-drop positions |
| sort_order | integer | |

### tables
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| floor_id | bigint FK | |
| branch_id | bigint FK | |
| name | varchar(50) | "T1", "VIP-3" |
| capacity | integer | Covers |
| status | enum | `available`, `occupied`, `reserved`, `cleaning` |
| merged_into_table_id | bigint FK nullable | When tables are merged |

### table_orders
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| table_id | bigint FK nullable | Null for takeaway/delivery |
| branch_id | bigint FK | |
| cashier_id | bigint FK → users | |
| shift_id | bigint FK | |
| pos_cart_id | bigint FK nullable | Linked POS cart |
| order_type | enum | `dine_in`, `takeaway`, `delivery`, `drive_thru` |
| cover_count | integer | Number of diners (dine-in) |
| status | enum | `open`, `kot_sent`, `served`, `billed`, `completed`, `cancelled` |
| service_charge | decimal(10,2) | Resolved at order creation |
| notes | text nullable | Special instructions |
| created_at / updated_at | timestamps | |

### kitchen_stations
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| branch_id | bigint FK | |
| name | varchar(100) | "Grill", "Cold Kitchen", "Bakery" |
| printer_id | bigint FK nullable | Linked printer (Phase 21) |
| sort_order | integer | |

### kot_tickets
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| table_order_id | bigint FK | |
| kitchen_station_id | bigint FK | |
| ticket_number | varchar(20) | e.g. KOT-2026-00451 |
| status | enum | `pending`, `preparing`, `ready`, `served` |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### kot_ticket_items
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| kot_ticket_id | bigint FK | |
| product_variant_id | bigint FK | |
| quantity | integer | |
| modifiers | json nullable | Applied modifier items |
| notes | text nullable | Item-level instruction |
| status | enum | `pending`, `preparing`, `ready`, `served` |

---

## 3. KOT Lifecycle State Machine

```
[Order Created]
      |
      | Cashier/Waiter confirms order
      ▼
[KOT Generated] — printed to kitchen station printer
      |
      | Kitchen marks "Preparing"
      ▼
   [Preparing]
      |
      | Kitchen marks "Ready"
      ▼
     [Ready] — notification to waiter/cashier
      |
      | Served to table
      ▼
    [Served] — table_order status → "served"
      |
      | Cashier initiates billing
      ▼
   [Billed] — POS checkout triggered → Sale created
      |
      | Payment complete
      ▼
  [Completed] — table status → "available"
```

---

## 4. Service Charge

- Config key: `restaurant.service_charge_type` (`none` / `fixed` / `percentage`).
- Config key: `restaurant.service_charge_value` (decimal).
- Config key: `restaurant.service_charge_tax_inclusive` (boolean).
- Applied automatically to dine-in `table_orders`; waivable by manager at billing time (logged).
- Passed to POS cart as a separate line item with `item_type = service_charge`.

---

## 5. Order Types

| Type | Table Required | Cover Count | Address |
| :--- | :---: | :---: | :---: |
| Dine-in | Yes | Yes | No |
| Takeaway | No | No | No |
| Delivery | No | No | Yes |
| Drive-thru | No | No | No |

Delivery orders store customer name, phone, and address in `table_orders.notes` JSON (full delivery address model in Phase 20).

---

## 6. Real-Time Updates

- KOT status changes broadcast on `restaurant.kitchen.{branchId}` channel (Reverb).
- Table status changes broadcast on `restaurant.floor.{branchId}` channel.
- Kitchen staff view updates in real-time on the KDS screen (Phase 20) without page refresh.

---

## 7. Admin UI — Phase 19 Scope

- **Restaurant → Floor Plan:** List floors; list tables per floor; table status colour-coded (green=available, red=occupied, amber=reserved, grey=cleaning); click table to open order.
- **Restaurant → New Order:** Select order type → for dine-in, select table → build cart (same product search as POS) → Confirm → KOT generated → order status = `kot_sent`.
- **Restaurant → Kitchen Stations:** CRUD for stations; assign printer (Phase 21 integration point).
- **Settings → Restaurant:** Service charge, order types enabled/disabled, KOT auto-print toggle.

---

## 8. API Endpoints

| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| GET | /api/v1/restaurant/floors | restaurant.view | List floors with tables |
| GET | /api/v1/restaurant/tables | restaurant.view | All tables with live status |
| PATCH | /api/v1/restaurant/tables/{id}/status | restaurant.manage | Update table status |
| POST | /api/v1/restaurant/orders | restaurant.orders | Create table order |
| GET | /api/v1/restaurant/orders/{id} | restaurant.orders | Get order with KOT tickets |
| POST | /api/v1/restaurant/orders/{id}/send-kot | restaurant.orders | Generate & send KOT |
| PATCH | /api/v1/restaurant/kot/{id}/status | restaurant.kitchen | Update KOT status |
| POST | /api/v1/restaurant/orders/{id}/bill | restaurant.orders | Initiate billing → create POS cart |
| GET | /api/v1/restaurant/kitchen-stations | restaurant.view | List stations for branch |

---

## 9. Services & Classes

- `RestaurantOrderService` — create order, send KOT, update status, bill order.
- `KotService` — generate KOT ticket numbers (sequential per branch per day), route to correct kitchen station, dispatch print job.
- `TableService` — status transitions, merge/unmerge tables.
- `ServiceChargeResolver` — reads config, calculates service charge amount for an order.
- `RestaurantEvents` — `KotStatusChanged`, `TableStatusChanged`, `OrderReady` (all broadcast via Reverb).
