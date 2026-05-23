# Phase 5 — Inventory & Warehouse Management

**SRS Reference:** §3.6, §3.18 (opening stock & import framework)  
**Status:** Complete  
**Depends on:** Phase 4

---

## Objective

Real-time **stock ledger**, immutable **stock movements**, inter-warehouse transfers, and negative-stock prevention — including concurrent reservation safety, the shared import framework (§3.18), and go-live cutover gating.

---

## Database

### Key Tables

| Table | Key Columns |
|---|---|
| `inventories` | `warehouse_id`, `product_variant_id`, `batch_id` (nullable), `quantity_on_hand`, `quantity_reserved` |
| `stock_movements` | `reason` (enum), `qty_delta`, `reference_type`, `reference_id`, `user_id`, `created_at` — immutable, no updates/deletes |
| `stock_transfers` | `from_warehouse_id`, `to_warehouse_id`, `status` (draft → shipped → partially_received → received) |
| `stock_transfer_items` | `stock_transfer_id`, `product_variant_id`, `batch_id`, `qty_requested`, `qty_received` |
| `stock_reservations` | `warehouse_id`, `product_variant_id`, `batch_id`, `quantity`, `reference_type`, `reference_id`, `expires_at`, `released_at` |
| `import_export_jobs` | `entity_type`, `mode`, `file_path`, `status`, `row_stats`, `user_id`, `tenant_id`, `warehouse_id` (nullable) |
| `branches` | `cutover_date` (nullable datetime) — blocks live POS sale deductions before go-live |
| `warehouses` | `is_active` (soft-deactivation; inventory rows retained) |

### `stock_movements.reason` Enum

Canonical values (other phases must extend this list, not create parallel enums):

- `opening_balance`
- `adjustment`
- `damaged`
- `sale`
- `sale_return`
- `transfer_out`
- `transfer_in`
- `reserved`
- `reservation_released`

**Extension in use:** `purchase_receive` — used by manual stock receive (not a POS/sale reason).

---

## Features

### Stock Ledger & Adjustments

- Stock adjustment UI with reason codes: `adjustment`, `damaged` (requires `inventory.adjust`).
- Bulk stock adjustment import for reason-coded corrections (requires `inventory.bulk-adjustment-import`).
- Batch/expiry required on any adjustment touching a batch-tracked variant (manual UI and import); batch-ambiguous rows rejected.
- Available quantity: `quantity_on_hand − quantity_reserved`.

### Concurrent Reservation Safety

- **Implemented:** pessimistic row-level locking (`SELECT … FOR UPDATE`) on `inventories` via `InventoryRepository::lockOrCreate` / `findForUpdate`.
- All deductions, reservations, releases, and opening-balance sets run inside DB transactions with locked rows.

### Transfer Workflow

- Lifecycle: **draft → shipped → partially_received → received**.
- **Partial receive:** `qty_received` may be less than `qty_requested`; unreceived qty stays in-transit until a later receive.
- Ship deducts `quantity_on_hand` at source; receive increases destination `quantity_on_hand` by received qty only.
- Cannot ship more than `quantity_on_hand − quantity_reserved` at source.

### Reservation Lifecycle

- `InventoryService::reserve` — locks row, checks available qty, increments `quantity_reserved`, creates `reserved` movement, records `stock_reservations` row with TTL.
- `InventoryService::release` — decrements `quantity_reserved`, creates `reservation_released` movement, marks reservation released.
- `InventoryService::deduct` — decrements `quantity_on_hand` and matching `quantity_reserved`, creates sale (or other) movement.
- **TTL job:** `inventory:release-expired-reservations` (scheduled every minute; default TTL 30 min via `config/inventory.php` / `INVENTORY_RESERVATION_TTL`).
- **Phase 7 hook:** cart hold / expiry / order placement call `reserve`, `release`, and `deduct` — service layer ready; no POS cart integration in this phase.

### FEFO / FIFO Picking Strategy

- Configurable per branch: `fefo` or `fifo`.
- Applies to batch-tracked variants during sale deduction when no explicit batch is supplied.
- Non-batch variants use FIFO by inventory insertion order.

### Negative-Stock Prevention

- `POST /api/pos/stock-check` — returns `can_sell` and per-line available qty.
- `POST /api/pos/stock-deduct` — server-side sale deduction with row lock (blocks even if client skips pre-check).
- Legacy alias: `POST /api/v1/inventory/check-availability` also returns `can_sell`.

### Go-Live Cutover Gate

- Branch setting: `cutover_date` (nullable datetime), editable on Branch edit screen.
- When set and `now() < cutover_date`, **Sale** deductions are blocked; manual adjust, receive, transfer, and import jobs are unaffected.

### Events

- `InventoryStockChanged` — fired after any committed change to `quantity_on_hand` or `quantity_reserved`.
- Payload includes: `warehouse_id`, `variant_id`, `batch_id`, `new_qty_on_hand`, `new_qty_reserved`, `reason` (plus broadcast fields for Phase 6 Reverb).

### Reports

- **Current stock by warehouse** — Admin → Stock levels: variant, batch, expiry, on-hand, reserved, available.
- Export via shared import/export framework (`inventory` entity, `inventory.reports` permission).

---

## Shared Import Framework (§3.18)

Established in this phase; reused by Phases 4, 8, 9, 10, 11.

### Components

- **Orchestration:** `ImportExportRegistry`, `ValidateImportJob`, `ProcessImportJob`, `GenerateErrorReportJob`, import wizard UI (`resources/js/Components/import-export/`).
- Row error export — downloadable from job detail / logs dialog.
- `import_export_jobs.warehouse_id` — column present for job-level warehouse scoping; per-row `warehouse_code` resolution used today (wizard warehouse picker deferred).

### Opening Stock Import

- **Entity:** `inventory`
- **Permission:** `inventory.import-opening-stock`
- **Template columns:** `warehouse_code`, `sku`, `qty`, `batch_no` (optional), `expiry_date` (optional)
- Creates `opening_balance` movements; **sets** `quantity_on_hand` directly.
- **Re-run behaviour:** reject duplicate (existing `opening_balance` movement for same warehouse + variant + batch).
- Queued via `ProcessImportJob`; progress in jobs tray.

### Bulk Stock Adjustment Import

- **Entity:** `inventory-adjustments`
- **Permission:** `inventory.bulk-adjustment-import`
- **Template columns:** `warehouse_code`, `sku`, `batch_no` (required if batch-tracked), `reason`, `qty_delta`, optional `notes`
- Only `adjustment` and `damaged` reasons accepted.

### Import Modes

| Mode | Behaviour |
|---|---|
| Strict | Any invalid row aborts the entire job |
| Non-strict (default) | Valid rows committed; invalid rows in error export |

---

## Services

| Method | Description |
|---|---|
| `InventoryService::reserve` | Locks row, checks available qty, increments `quantity_reserved`, creates `reserved` movement + reservation record |
| `InventoryService::release` | Decrements `quantity_reserved`, creates `reservation_released` movement |
| `InventoryService::deduct` | Decrements `quantity_on_hand` and `quantity_reserved`, creates movement (e.g. `sale`) |
| `InventoryService::setOpeningBalance` | Sets on-hand for go-live import; rejects duplicate opening balance |

All committed inventory mutations fire `InventoryStockChanged`.

---

## Permissions Reference

| Gate | Description |
|---|---|
| `inventory.view` | Legacy view access (reports prefer `inventory.reports`) |
| `inventory.reports` | Stock levels report and export |
| `inventory.adjust` | Manual stock adjustment |
| `inventory.receive` | Manual stock receive |
| `inventory.bulk-adjustment-import` | Bulk adjustment import |
| `inventory.import-opening-stock` | Opening balance import |
| `inventory.transfer` | Create and manage stock transfers |

---

## Acceptance Criteria

| # | Criterion | Status |
|---|---|---|
| 1 | Receive increases on-hand + movement with correct reason | Met |
| 2 | `deduct` decreases on-hand and reserved + emits event | Met |
| 3 | Cannot over-ship transfer at source | Met |
| 4 | Partial receive updates `qty_received`, destination on-hand, `partially_received` status | Met |
| 5 | POS stock-check blocks insufficient sale; deduct uses row lock | Met |
| 6 | Reservation TTL job + `reservation_released` movements | Met |
| 7 | Opening stock import via queue; on-hand matches file; job stats logged | Met |
| 8 | Non-strict import: valid rows commit, error CSV for invalid rows | Met |
| 9 | Batch-ambiguous adjustment rejected (import + manual) | Met |
| 10 | `InventoryStockChanged` on service mutations with full payload | Met |
| 11 | Report shows on-hand, reserved, available separately | Met |
| 12 | Future `cutover_date` blocks POS deduct; adjust/import unaffected | Met |

---

## Decisions (Resolved)

| # | Question | Decision |
|---|---|---|
| 1 | Concurrency strategy | **Pessimistic** (`SELECT FOR UPDATE` on `inventories`) |
| 2 | Opening stock re-import | **Reject duplicate** per warehouse + variant + batch |
| 3 | Partial transfer status | **Distinct** `partially_received` status |
| 4 | Warehouse deactivation | **Soft-delete** via `warehouses.is_active`; inventory retained; inactive warehouses excluded from pickers |

---

## Phase 7 Handoff

- Wire POS cart to `InventoryService::reserve`, `::release`, and `::deduct`.
- Optional: set `import_export_jobs.warehouse_id` in import wizard for branch-scoped jobs.
- Optional: tenant reservation TTL in Admin → Settings (currently `config/inventory.php`).

---

## Key Implementation Paths

| Area | Location |
|---|---|
| Inventory service | `app/Services/InventoryService.php` |
| Transfers | `app/Services/StockTransferService.php` |
| POS API | `app/Http/Controllers/Api/Pos/InventoryController.php`, `routes/api.php` |
| Import handlers | `app/Services/ImportExport/Handlers/Inventory*.php` |
| TTL command | `app/Console/Commands/ReleaseExpiredInventoryReservationsCommand.php` |
| Stock UI | `resources/js/Pages/Admin/Inventory/` |
| Transfer UI | `resources/js/Pages/Admin/StockTransfers/` |
