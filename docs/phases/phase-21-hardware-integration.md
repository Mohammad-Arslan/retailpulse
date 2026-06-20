# Phase 21 — Hardware Integration Layer

**SRS Reference:** §3.22
**Status:** Planned
**Depends on:** Phase 8 (Checkout — receipt printing), Phase 17 (Shifts — cash drawer on open/close)
**Feeds into:** Phase 19 (Restaurant — kitchen printer routing via hardware layer)

---

## Objective
Abstract all physical POS hardware behind a consistent service layer so that device changes require only a configuration update — not code changes. Deliver: receipt printer auto-print on sale, cash drawer auto-open on payment, barcode scanner input handling, kitchen printer routing, and stub interfaces for scales and card terminals.

---

## 1. Data Model

### printers
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| branch_id | bigint FK | |
| name | varchar(100) | "Counter Receipt", "Kitchen Grill" |
| type | enum | `receipt`, `kitchen`, `label` |
| connection_type | enum | `network`, `usb`, `serial` |
| config | json | IP+port for network; vendor_id+product_id for USB; port+baud for serial |
| is_default_receipt | boolean | Used when no printer is specified |
| is_active | boolean | |
| created_at / updated_at | timestamps | |

### devices
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| branch_id | bigint FK | |
| name | varchar(100) | |
| type | enum | `cash_drawer`, `scale`, `customer_display`, `card_terminal` |
| config | json | Device-specific config |
| linked_printer_id | bigint FK nullable | Cash drawer linked to receipt printer |
| is_active | boolean | |

---

## 2. ESC/POS Print Dispatch Service

`PrintService::dispatch(PrintJob $job): void`

- Resolves the target printer: job specifies `printer_id` or falls back to `is_default_receipt` for the branch.
- Builds ESC/POS byte sequence from the `PrintJob` payload (items list, totals, logo, QR code).
- Dispatches via the appropriate driver:
    - **Network (TCP):** Opens socket to IP:port, writes ESC/POS bytes, closes socket. Implemented in `NetworkPrinterDriver`.
    - **USB:** Queues a `PrintToUsbJob`; the browser-side POS JavaScript picks it up via a long-poll endpoint and writes to the USB device using WebUSB API. Implemented in `UsbPrinterDriver`.
    - **Serial:** Similar to USB but uses Web Serial API. Implemented in `SerialPrinterDriver`.
- Failed print jobs are retried twice then logged to `print_job_failures` (id, printer_id, payload, error, created_at).

### Receipt Print Trigger Points
- Sale completion → `SaleCompletedEvent` → `PrintReceiptListener` → `PrintService::dispatch`.
- Z-report generation (Phase 17).
- KOT generation (Phase 19) → routed to kitchen station's linked printer.

---

## 3. Cash Drawer

- `CashDrawerService::open(Register $register, string $reason): void`
    1. Resolves the default receipt printer for the register's branch.
    2. Sends ESC/POS drawer-open command (`ESC p m t1 t2`) via `PrintService`.
    3. Logs to `shift_cash_movements` (Phase 17) with the reason.
- Trigger points: cash payment accepted at checkout, manual no-sale (cashier requests open from POS menu).
- If no printer is configured, `CashDrawerService` falls back to a browser-side `window.postMessage` to a companion Electron/PWA app (future scope; stub only in this phase).

---

## 4. Barcode Scanner

**USB HID (keyboard wedge — zero config required)**
- USB scanners in keyboard-wedge mode send keystrokes directly to the browser; the POS search field captures them.
- A prefix/suffix decoder in the POS React component detects rapid keystrokes ending in Enter as a scanner input vs. manual typing (debounce + input rate threshold).

**Camera (mobile / webcam)**
- `BarcodeScanner` React component wraps the browser Barcode Detection API (`BarcodeDetector`) with a fallback to `quagga2`.
- Activated by a camera icon in the POS search field; streams video until a barcode is decoded.
- Decoded value submitted to the same product search handler as keyboard input.

---

## 5. Weighing Scales (Grocery/Mart)

- `ScaleService::readWeight(Device $scale): WeightReading|null` — uses Web Serial API (browser-side) to connect to a serial-port scale.
- Returns `WeightReading { value: float, unit: 'kg'|'g'|'lb' }`.
- At POS, when a scale device is configured for the branch and the selected variant's unit is weight-based, a "Read Scale" button appears next to the quantity field.
- Stub implementation: if Web Serial is not available, the user enters weight manually.

---

## 6. Customer-Facing Display

- A secondary browser window (`/pos/customer-display`) opened from the POS by the cashier.
- Receives cart updates via `BroadcastChannel` API (same-origin cross-tab messaging).
- Displays: current item list, subtotal, any applied discount, and a promotional banner image (configurable in Settings).
- No authentication required — purely display; the POS window is the controller.

---

## 7. Card Terminals (Bank-Provided Stubs)

`CardTerminalInterface` with methods:
- `initiatePayment(Money $amount): PaymentRequest`
- `checkStatus(string $transactionId): PaymentStatus`
- `voidTransaction(string $transactionId): bool`

Stub implementations: `VerifoneStub`, `IngenicosStub` — log the request and return a success response.
Real implementations registered per branch via `payment_gateway_configs` (existing Phase 8 table).

---

## 8. Admin UI — Settings → Hardware

- **Printers tab:** List printers for the branch; Add/Edit/Delete; "Test Print" button sends a test ESC/POS page.
- **Devices tab:** List devices; Add/Edit/Delete; "Open Drawer" test button.
- **Printer Profiles:** Assign default receipt printer, default kitchen printer(s) per station.

---

## 9. API Endpoints

| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| GET | /api/v1/devices | hardware.view | List devices for branch |
| POST | /api/v1/devices | hardware.manage | Register device |
| PUT | /api/v1/devices/{id} | hardware.manage | Update device config |
| DELETE | /api/v1/devices/{id} | hardware.manage | Deregister device |
| POST | /api/v1/devices/{id}/test-print | hardware.manage | Dispatch test print job |
| GET | /api/v1/printers | hardware.view | List printers |
| POST | /api/v1/printers | hardware.manage | Register printer |
| PUT | /api/v1/printers/{id} | hardware.manage | Update printer |

---

## 10. Services & Classes

- `PrintService` — job dispatch with driver resolution.
- `NetworkPrinterDriver`, `UsbPrinterDriver`, `SerialPrinterDriver` — connection-type drivers.
- `EscPosBuilder` — fluent builder for ESC/POS byte sequences (header, items, totals, QR, cut).
- `CashDrawerService` — open drawer with audit log.
- `ScaleService` — Web Serial weight reader (browser-side JS + PHP endpoint for config retrieval).
- `CardTerminalInterface` + stubs.
- `PrintReceiptListener`, `PrintKotListener` — event listeners wiring sales/KOT events to `PrintService`.
