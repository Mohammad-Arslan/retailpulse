# Phase 5 — Inventory & Warehouse Management

**SRS Reference:** §3.6  
**Status:** Planned  
**Depends on:** Phase 4

---

## Objective

Real-time **stock ledger**, immutable **stock movements**, inter-warehouse transfers, and negative-stock prevention.

## Database (key tables)

- `inventories` — warehouse_id, product_variant_id, batch_id (nullable), quantity_on_hand, quantity_reserved
- `stock_movements` — immutable: reason enum, qty_delta, reference_type/id, user_id, created_at
- `stock_transfers` — from_warehouse, to_warehouse, status (draft, shipped, received)
- `stock_transfer_items` — variant_id, batch_id, qty

## Features

- Stock adjustment UI (reason: adjustment, damaged)
- Transfer workflow: create → ship → receive (updates both warehouses)
- FEFO/FIFO picking strategy config (per branch)
- Reserve stock on cart hold (hook for Phase 7)
- Service: `InventoryService::deduct`, `::reserve`, `::release`
- Event: `InventoryStockChanged` (prep for Reverb in Phase 6)
- Reports: current stock by warehouse (basic table)

## Acceptance Criteria

1. Receive stock increases `quantity_on_hand`; sale deduction creates movement row.
2. Cannot transfer more than on-hand quantity.
3. System blocks sale when stock insufficient (API endpoint for POS).
