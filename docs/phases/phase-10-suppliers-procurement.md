# Phase 10 — Supplier & Purchase Order Management

**SRS Reference:** §3.10, §3.18 (suppliers)  
**Status:** Planned  
**Depends on:** Phase 4, Phase 3 (warehouse CRUD follow-up recommended before multi-warehouse GRN)

---

## Objective

Full **procurement cycle** with approval workflows and supplier ledger.

## Database (key tables)

- `suppliers` — contact, terms, balance
- `purchase_orders`, `purchase_order_items`
- `goods_receiving_notes`, `grn_items`
- `supplier_invoices`, `supplier_payments`
- `supplier_ledger_entries` — running payable balance

## Features

- PO create → send (email/PDF) → approval if amount > threshold
- GRN against PO → updates inventory (stock movement `purchase_receive`) at selected **warehouse** (active warehouses for branch via Phase 3 warehouse CRUD)
- Supplier invoice against GRN
- Payment recording against invoice
- Approval: Branch Manager / Owner PIN or permission
- Permissions: `procurement.*`, `procurement.approve-po`
- **Bulk import/export (§3.18):** suppliers CSV/Excel; optional historical purchase headers with `is_historical` (reporting only); `suppliers.import`, `suppliers.export`

## Acceptance Criteria

1. PO → GRN → Invoice → Payment flow updates stock and supplier balance correctly.
2. PO over configured amount requires approval before send.
3. Supplier list import creates 50+ suppliers with validation errors surfaced per row.

---

## Phase Enhancements (SRS v4.0)

### Purchase Approval Workflow Hook (§3.30 — Phase 29)
- The existing approval threshold logic (PO amount > configurable limit requires manager PIN) is extended to optionally route through the generic Workflow Engine when Phase 29 is active.
- When `feature_flags.procurement.workflow_approval` is enabled, PO approval triggers a `WorkflowInstance` instead of a direct PIN prompt.
- Backwards compatible: when the feature flag is off, the existing PIN-based approval remains.
- PO escalation after N hours of no approver action (configurable per branch).

### Supplier Performance Scoring (stub)
- `suppliers` table gains: `on_time_delivery_rate` (decimal), `quality_rejection_rate` (decimal), `last_scored_at` (timestamp).
- A scheduled job (monthly) calculates scores from GRN delivery dates vs PO expected dates and rejection notes.
- Scores are displayed on the Supplier show page; no automated action taken (advisory only in this phase).

---

## SRS v4.0 Enhancements (§3.10)

### 3-Way PO Matching

- **`po_match_results`** — `purchase_order_id`, `grn_id`, `supplier_invoice_id`, `match_status` (`fully_matched`/`partially_matched`/`unmatched`), `qty_variance`, `price_variance`, `matched_by`, `matched_at`, `exception_reason`.
- Auto-compare invoice lines → GRN lines → PO lines on supplier invoice entry.
- Configurable tolerances (default ±2% price, ±0 qty).
- Only `fully_matched` invoices approvable for payment; exceptions routed to Procurement Officer.
- Permission: `procurement.resolve-match-exception`.

### Supplier Price Lists & Contracts

- **`supplier_price_lists`** — `supplier_id`, `name`, `valid_from`, `valid_to`, `currency_code`.
- **`supplier_price_list_items`** — `price_list_id`, `product_variant_id`, `unit_price`, `min_qty`, `lead_time_days`.
- PO line auto-populates unit price from active price list; override requires reason.
- Expiry alert notification N days before `valid_to`.
- Bulk import/export of supplier price lists (§3.18).

### Purchase Return & RMA

- **`purchase_returns` / `purchase_return_items`** — full lifecycle from posted GRN.
- Statuses: `draft` → `approved` → `goods_dispatched` → `supplier_acknowledged` → `debit_note_issued` → `closed`.
- **`debit_notes`** — numbered supplier-facing document; reduces AP balance.
- Stock movement `return_supplier` on `goods_dispatched`; GL: Debit AP, Credit Inventory.

### Landed Cost Allocation

- **`landed_cost_entries` / `landed_cost_allocations`** — freight, duty, insurance, etc. per GRN.
- Allocation methods: quantity, weight, value, manual per line.
- Allocated amount updates effective unit cost in `inventory_cost_layers` (Phase 11).

### Drop-Shipping

- `purchase_orders.drop_ship` flag + link to customer sale order.
- Virtual receive-then-ship movement; no warehouse on-hand change.
- GRN confirmation triggers customer invoice and shipment notification.

### Acceptance Criteria (v4.0)

1. Supplier invoice with matching PO/GRN lines reaches `fully_matched` and is payable.
2. Price mismatch beyond tolerance flags `partially_matched` and blocks payment until resolved.
3. Purchase return from GRN reduces stock and issues debit note with correct GL posting.
4. Landed cost on GRN increases FIFO layer unit cost.
5. Drop-ship PO does not change warehouse on-hand; customer invoice generated on GRN confirm.
