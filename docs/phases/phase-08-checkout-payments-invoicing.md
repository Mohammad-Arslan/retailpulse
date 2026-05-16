# Phase 8 — Checkout, Payments & Invoicing

**SRS Reference:** §3.8, §3.18 (historical sales archive)  
**Status:** Planned  
**Depends on:** Phase 7

---

## Objective

Complete **sale transaction**: payments, split tender, layaway, credit sales, and printable/shareable invoices.

## Database (key tables)

- `sales` — branch_id, warehouse_id, customer_id, cashier_id, status, subtotal, tax, total, balance_due
- `sale_items` — variant_id, qty, unit_price, tax, discount
- `sale_payments` — method enum, amount, reference, gateway_response (JSON)
- `sale_invoices` — number, template, pdf_path, public_token

## Features

- Unified payment screen: cash, card, mobile wallet (Stripe/JazzCash stubs), bank transfer
- Split tender (multiple `sale_payments` per sale)
- Layaway/deposit: `balance_due` > 0, partial payments
- Credit sale: link to customer account (Phase 9)
- Auto-post inventory movements + `pos.sale.completed.{branchId}` broadcast
- Invoice templates: thermal 80mm + A4 (DomPDF)
- Share: email, public link, WhatsApp API stub
- Permissions: `sales.create`, `sales.refund` (void only; full refund Phase 14)
- **Historical sales import (§3.18, optional):** bulk load past transactions with `is_historical = true`; no inventory deduction, no live journal posting; enables trend reports before go-live
- Permissions: `sales.import-historical`, `sales.export`

## Acceptance Criteria

1. $100 sale paid $60 card + $40 cash creates two payment rows.
2. Layaway records balance; second payment closes sale.
3. PDF invoice generates and downloads in < 3s for 20-line sale.
4. Historical sale import does not change `quantity_on_hand`; dashboard sales KPIs exclude historical rows by default.
