# Phase 5 — Inventory & Warehouse Management

**SRS Reference:** §3.6, §3.18 (opening stock & import framework)  
**Status:** Complete  
**Depends on:** Phase 4

---

## Objective

Real-time **stock ledger**, immutable **stock movements**, inter-warehouse transfers, and negative-stock prevention.

## Database (key tables)

- `inventories` — warehouse_id, product_variant_id, batch_id (nullable), quantity_on_hand, quantity_reserved
- `stock_movements` — immutable: reason enum, qty_delta, reference_type/id, user_id, created_at
- `stock_transfers` — from_warehouse, to_warehouse, status (draft, shipped, received)
- `stock_transfer_items` — variant_id, batch_id, qty
- `import_export_jobs` — entity_type, mode, file_path, status, row_stats, user_id, `tenant_id` (shared import framework for §3.18)

## Features

- Stock adjustment UI (reason: adjustment, damaged)
- Transfer workflow: create → ship → receive (updates both warehouses)
- FEFO/FIFO picking strategy config (per branch)
- Reserve stock on cart hold (hook for Phase 7)
- Service: `InventoryService::deduct`, `::reserve`, `::release`
- Event: `InventoryStockChanged` (prep for Reverb in Phase 6)
- Reports: current stock by warehouse (basic table)
- **Shared import framework (§3.18):** `ImportExportService`, queued `ProcessImportJob`, validate-then-commit UI, row error export (used by Phases 4, 9, 10, 11, 8)
- **Opening stock import:** template columns `warehouse_code`, `sku`, `qty`, optional `batch_no`, `expiry_date`; creates `opening_balance` stock movements; requires `inventory.import-opening-stock`
- **Bulk stock adjustment import:** reason-coded corrections (manager permission)
- **Go-live cutover date** setting (branch/tenant): blocks live POS stock deductions before cutover if configured

## Acceptance Criteria

1. Receive stock increases `quantity_on_hand`; sale deduction creates movement row.
2. Cannot transfer more than on-hand quantity.
3. System blocks sale when stock insufficient (API endpoint for POS).
4. Opening stock import for 500 SKUs completes via queue; on-hand matches file; audit job logged.
5. Import job with 10 invalid SKUs returns error file; 490 valid SKUs still applied (non-strict mode).
