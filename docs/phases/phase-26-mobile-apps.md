# Phase 26 — Mobile Applications

**SRS Reference:** §3.28
**Status:** Planned
**Depends on:** Phase 23 (Module Config Engine — mobile module gate), Phase 25 (E-Commerce — customer order history)
**Feeds into:** Phase 28 (SaaS — mobile access gated per subscription plan)

---

## Objective
Define the API contract and architecture for four React Native mobile applications — Customer, Waiter, Inventory Scanner, and Manager — each authenticated via Sanctum mobile tokens with app-specific scopes, and integrated with Firebase FCM for push notifications.

---

## 1. Technology Choices

| Decision | Choice | Rationale |
| :--- | :--- | :--- |
| Framework | React Native (Expo managed workflow) | Shared React knowledge; Expo simplifies builds |
| Auth | Sanctum personal access tokens (mobile scope) | Consistent with existing API auth; no new auth layer |
| Push | Firebase Cloud Messaging (FCM) | Cross-platform; Laravel FCM package available |
| Offline (Scanner App) | WatermelonDB or AsyncStorage queue | Lightweight; works with React Native |
| Navigation | React Navigation v6 | Standard React Native navigation |

---

## 2. Sanctum Mobile Token Scopes

New token abilities (extend existing Sanctum abilities list):

| Scope | Description |
| :--- | :--- |
| `mobile:customer` | Customer app: loyalty, receipts, wallet |
| `mobile:waiter` | Waiter app: table orders, KOT status |
| `mobile:scanner` | Scanner app: GRN receive, stock count |
| `mobile:manager` | Manager app: KPIs, approvals, shift summary |

Token issuance endpoint: `POST /api/v1/mobile/auth/login`
- Request: `{ email, password, device_name, app_type }`.
- Returns: `{ token, user, permissions, enabled_modules }`.
- Device token (FCM registration token) submitted separately: `POST /api/v1/mobile/auth/device-token`.

---

## 3. Customer App

**Screens:** Home (loyalty balance + tier), Points History, Digital Receipts, Wallet (balance + top-up), Store Locator, Profile.

**Key API Endpoints (mobile:customer scope):**
```
GET  /api/v1/mobile/customer/profile
GET  /api/v1/mobile/customer/loyalty
GET  /api/v1/mobile/customer/receipts
GET  /api/v1/mobile/customer/receipts/{id}       (view PDF receipt)
GET  /api/v1/mobile/customer/wallet
POST /api/v1/mobile/customer/wallet/topup        (initiates payment)
GET  /api/v1/mobile/customer/stores              (branch list with hours)
```

**Push Notifications:**
- "Your order is ready for pickup." (restaurant takeaway)
- "You earned 150 points on your purchase."
- "Your loyalty tier has been upgraded to Gold!"
- "Your gift card balance is low."

---

## 4. Waiter App

**Screens:** Floor Map, Table Detail (active order), New Order (product catalogue), KOT Status, Bill Request.

**Key API Endpoints (mobile:waiter scope):**
```
GET  /api/v1/restaurant/floors
GET  /api/v1/restaurant/tables
POST /api/v1/restaurant/orders
PATCH /api/v1/restaurant/orders/{id}             (add items)
POST /api/v1/restaurant/orders/{id}/send-kot
PATCH /api/v1/restaurant/kot/{id}/status
POST /api/v1/restaurant/orders/{id}/bill
```

**Push Notifications:**
- "KOT #451 is ready — Table T3." (kitchen marks ready)
- "Table T5 has been waiting 25 minutes." (long wait alert)

**Offline:** Waiter app requires connectivity for order submission; shows a connectivity banner if offline.

---

## 5. Inventory Scanner App

**Screens:** Home (quick actions), Scan to Receive GRN, Stock Count, Transfer Confirm, Barcode Lookup.

**Key API Endpoints (mobile:scanner scope):**
```
GET  /api/v1/mobile/scanner/grn/{id}             (GRN to receive)
POST /api/v1/mobile/scanner/grn/{id}/receive-line (receive individual line)
POST /api/v1/mobile/scanner/stock-count          (submit count batch)
PATCH /api/v1/stock-transfers/{id}/receive-item  (confirm transfer item)
GET  /api/v1/mobile/scanner/product/{barcode}    (product lookup)
```

**Offline Support:**
- Scanned items queued in WatermelonDB local store.
- Synced to server on reconnect with conflict detection (e.g., GRN already received by someone else).
- Sync status indicator in app header.

**Push Notifications:**
- "Stock transfer #T-2045 is awaiting your confirmation."
- "A stock count has been initiated for your branch."

---

## 6. Manager Dashboard App

**Screens:** Live KPIs, Low Stock Alerts, Pending Approvals (PO, refund, discount, payroll), Active Shifts, Sales Trend Chart.

**Key API Endpoints (mobile:manager scope):**
```
GET  /api/v1/mobile/manager/kpis                 (today's sales, profit, txn count)
GET  /api/v1/mobile/manager/alerts               (low stock, pending approvals)
GET  /api/v1/mobile/manager/approvals            (workflow instances awaiting manager)
POST /api/v1/mobile/manager/approvals/{id}/approve
POST /api/v1/mobile/manager/approvals/{id}/reject
GET  /api/v1/mobile/manager/shifts               (active shifts with cash summaries)
```

**Push Notifications:**
- "Suspicious activity: 5 voids by cashier Ahmed in last hour."
- "Low stock: Espresso Beans — 200g remaining."
- "PO #P-1045 requires your approval."
- "Shift variance PKR 500 requires your approval at Counter A."

---

## 7. FCM Integration

**Server-side (Laravel):**
- `fcm_device_tokens` table: `id`, `user_id`, `device_token`, `app_type`, `platform` (`ios`/`android`), `created_at`.
- `FcmNotificationChannel` — Laravel notification channel that sends via Firebase Admin SDK (or `laravel-notification-channels/fcm` package).
- All existing `Notification` classes gain an `toFcm()` method returning the push payload.

**Security:** Device tokens rotated on each login; old tokens deregistered. FCM `invalid-registration-token` responses automatically delete the token from the DB.

---

## 8. Services & Classes

- `MobileAuthController` — login, device token registration, logout.
- `CustomerMobileController`, `WaiterMobileController`, `ScannerMobileController`, `ManagerMobileController` — mobile-specific API controllers.
- `FcmNotificationChannel` — Laravel notification channel.
- `FcmDeviceToken` model + `FcmDeviceTokenService`.
- `OfflineSyncController` — receives queued offline scanner actions and processes them with conflict detection.
