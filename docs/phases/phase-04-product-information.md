# Phase 4 — Product Information Management (PIM)

**SRS Reference:** §3.5  
**Status:** Complete  
**Depends on:** Phase 3

---

## Objective

Central **product master data** with rich product types, automated identifiers, and batch/expiry tracking metadata.

## Database (key tables)

- `categories`, `brands`, `units`
- `products` — type enum (standard, variable, service, digital, serialized, combo), name, slug, tax_group_id (nullable until Phase 14)
- `product_variants` — sku, barcode, cost_price, sell_price, attributes (JSON)
- `product_batches` — batch_no, expiry_date, variant_id
- `product_bundle_items` — parent_variant_id, child_variant_id, qty
- `product_serials` — serial_number, variant_id, status
- Identifier sequences table for SKU/barcode patterns (EAN-13, UPC-A, CODE128)

## Features

- CRUD for categories, brands, products with variants
- Variable products: attribute sets (Size, Color) → auto-generate variants
- Combo/bundle builder UI
- Serialized product flag + serial capture on receive (Phase 5)
- Barcode/SKU auto-generation from configurable patterns
- Product change audit trail (via `audit_logs`)
- Branch pricing overrides table: `branch_product_prices`
- Permissions: `products.*`, `products.show-cost` (UI cost column gate)

## Acceptance Criteria

1. Create standard and variable products with generated SKUs/barcodes.
2. Combo product resolves child line items for future POS.
3. Product edits appear in audit log with old/new JSON.
