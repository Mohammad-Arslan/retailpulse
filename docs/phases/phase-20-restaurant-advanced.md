# Phase 20 — Restaurant Advanced

**SRS Reference:** §3.19
**Status:** Planned
**Depends on:** Phase 19 (Restaurant Core)
**Feeds into:** Phase 22 (Recipe & Ingredients), Phase 26 (Mobile — Waiter App)

---

## Objective
Layer advanced restaurant capabilities on top of Phase 19's core: a tablet-optimised waiter panel, kitchen display system (KDS), split billing, modifier system, combo meals, reservation system, and third-party delivery API stubs.

---

## 1. Waiter Panel

A dedicated tablet-optimised React layout (`/restaurant/waiter`) with:

- **Floor map view:** Colour-coded table tiles; tap to open the table's active order or create a new order.
- **Order taking:** Category tabs → product cards with photo, name, price → tap to add to order. Modifier selection sheet slides up if the product has modifier groups.
- **KOT status column:** Right sidebar shows KOT ticket statuses for the current table in real-time.
- **Bill request:** One-tap "Send to Cashier" button; sets order status to `billed` and alerts the POS.
- **Waiter assignment:** Orders are tagged with the creating waiter's user_id; managers can reassign via the waiter panel header.

### Data Model Additions
```sql
ALTER TABLE table_orders ADD COLUMN waiter_id BIGINT UNSIGNED NULL;
ALTER TABLE table_orders ADD FOREIGN KEY (waiter_id) REFERENCES users(id);
```

---

## 2. Kitchen Display System (KDS)

A full-screen browser page (`/restaurant/kds/{station_id}`) intended for a monitor mounted in the kitchen.

- Displays all `pending` and `preparing` KOT tickets for the station in real-time via Reverb.
- Each ticket card shows: ticket number, table/order name, items list, elapsed time since creation (green → amber → red as time increases).
- Kitchen staff tap item row to mark it `preparing`; tap ticket header to mark whole ticket `ready`.
- Tickets disappear from the KDS when marked `served`.
- No login required for the KDS page — it uses a branch-scoped station token (generated in Settings → Kitchen Stations) to authenticate the WebSocket channel.

---

## 3. Split Billing

`SplitBillingService::split(TableOrder $order, SplitStrategy $strategy): SplitResult`

### Split by Item Assignment
- Waiter drags items onto guest "buckets" (Guest 1, Guest 2, …).
- Each bucket generates an independent `Sale` and `SaleInvoice`.
- Unassigned items remain on the main order.

### Equal Split
- Total (including service charge, tax) divided equally across N guests.
- Each guest gets an invoice for 1/N of the total (rounded up for the last guest to avoid rounding loss).

### UI
- "Split Bill" button on the billing screen opens a split modal with item drag-drop or equal-split toggle.

---

## 4. Modifier System

### Data Model
```sql
modifier_groups
  id, name, branch_id, selection_type ENUM('single','multiple'), min_selections, max_selections

modifiers
  id, modifier_group_id, name, price_delta DECIMAL(10,2) DEFAULT 0, is_default BOOLEAN

product_variant_modifier_groups (pivot)
  product_variant_id, modifier_group_id, sort_order
```

- At order taking, when a variant with modifier groups is added, a bottom sheet shows the groups; required groups must be satisfied before adding the item.
- Modifier selections stored in `kot_ticket_items.modifiers` (JSON array of modifier IDs + names + price deltas).
- Modifier price deltas added to the line item unit price.

---

## 5. Reservation System

### Data Model
```sql
reservations
  id, branch_id, table_id (nullable; assigned on arrival), guest_name, guest_phone,
  cover_count, reserved_for DATETIME, notes TEXT, status ENUM('pending','confirmed','seated','cancelled','no_show'),
  created_by BIGINT FK users, created_at, updated_at
```

- **Booking flow:** Host creates reservation in Admin → Restaurant → Reservations; system checks table availability for the time slot.
- **Reminder notification:** `ReservationReminderJob` dispatched N minutes before `reserved_for` (configurable: default 30 min); sends SMS via Twilio or in-app notification.
- **Arrival:** Host marks reservation `seated`; table status changes to `occupied`; a new `table_order` is auto-created linked to the reservation.

---

## 6. Third-Party Delivery API Stubs

### Delivery Orders Enhancement
- `table_orders` extended for delivery:

```sql
ALTER TABLE table_orders
  ADD COLUMN delivery_address JSON NULL,
  ADD COLUMN rider_id BIGINT UNSIGNED NULL,
  ADD COLUMN delivery_status ENUM('pending','assigned','picked_up','delivered','failed') NULL,
  ADD COLUMN estimated_delivery_at TIMESTAMP NULL;
```

- Internal rider assignment: admin assigns a staff member as rider; delivery status updated via Admin → Deliveries or Waiter App.
- External delivery stub interface: `DeliveryProviderInterface` with `FoodpandaProvider` and `UberEatsProvider` stub implementations. Credentials configured per branch in integration settings; stubs log the payload but do not make real API calls until credentials are active.

---

## 7. Combo Meals (Enhancement to Phase 4)

- Combo products (`product_type = combo`) already exist from Phase 4 (PIM).
- Restaurant enhancement: combo items can include modifier overrides (e.g., a "Meal Deal" where the drink modifier can be chosen at POS).
- `product_bundle_items` gains an `allow_modifier_override` boolean column.

---

## 8. Admin UI Additions

- **Restaurant → Reservations:** Calendar + list view; create, confirm, seat, cancel.
- **Restaurant → Deliveries:** Active delivery orders with rider assignment and status tracking.
- **Settings → Modifiers:** Modifier group CRUD; attach groups to variants.
- **KDS page:** Full-screen route at `/restaurant/kds/{stationId}`.
- **Waiter Panel:** Tablet-optimised route at `/restaurant/waiter`.

---

## 9. Services & Classes

- `WaiterOrderService` — waiter-specific order actions (add item, apply modifier, request bill, transfer order to another waiter).
- `SplitBillingService` — split-by-item and equal-split strategies.
- `ModifierService` — resolve modifier groups for a variant, validate selections, calculate price delta.
- `ReservationService` — create, confirm, seat, cancel; availability check.
- `DeliveryService` — rider assignment, status updates, provider stub dispatch.
- `ReservationReminderJob` — scheduled notification dispatch.
