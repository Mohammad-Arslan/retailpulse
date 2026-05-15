# Phase 10 — Supplier & Purchase Order Management

**SRS Reference:** §3.10  
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

## Acceptance Criteria

1. PO → GRN → Invoice → Payment flow updates stock and supplier balance correctly.
2. PO over configured amount requires approval before send.
