# Phase 10 — Supplier & Purchase Order Management

**SRS Reference:** §3.10, §3.18 (suppliers)  
**Status:** Planned  
**Depends on:** Phase 4, Phase 5

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
- GRN against PO → updates inventory (stock movement `purchase_receive`)
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

## Phase Enhancements (SRS v3.0)

### Purchase Approval Workflow Hook (§3.29 — Phase 29)
- The existing approval threshold logic (PO amount > configurable limit requires manager PIN) is extended to optionally route through the generic Workflow Engine when Phase 29 is active.
- When `feature_flags.procurement.workflow_approval` is enabled, PO approval triggers a `WorkflowInstance` instead of a direct PIN prompt.
- Backwards compatible: when the feature flag is off, the existing PIN-based approval remains.

### Supplier Performance Scoring (stub)
- `suppliers` table gains: `on_time_delivery_rate` (decimal), `quality_rejection_rate` (decimal), `last_scored_at` (timestamp).
- A scheduled job (monthly) calculates scores from GRN delivery dates vs PO expected dates and rejection notes.
- Scores are displayed on the Supplier show page; no automated action taken (advisory only in this phase).
