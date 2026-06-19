# Phase 4 — Product Information Management (PIM)

**SRS Reference:** §3.5, §3.18 (products & catalog)  
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
- **Bulk import/export (§3.18):**
  - Download Excel/CSV templates for categories, brands, units, products (standard + variable)
  - Import: validate → preview errors → queue job; modes `create`, `update`, `upsert` on SKU or barcode
  - Export: full catalog or filtered list (respects `products.show-cost`)
  - Permissions: `products.import`, `products.export`

## Acceptance Criteria

1. Create standard and variable products with generated SKUs/barcodes.
2. Combo product resolves child line items for future POS.
3. Product edits appear in audit log with old/new JSON.
4. Operator imports 100+ products from template; invalid rows reported without blocking valid rows.
5. Operator exports catalog to Excel; re-import with `upsert` updates prices without duplicate SKUs.

---

## SRS v4.0 Enhancements (§3.5)

### Preferred Supplier per Variant

- `product_variants` gains `preferred_supplier_id` (nullable FK → `suppliers`) and optional `alternate_supplier_ids` (JSON array of supplier IDs).
- PIM variant edit UI: supplier picker with primary + alternates.
- Auto-reorder engine (Phase 5) uses `preferred_supplier_id` when creating draft POs from `LowStockAlert` events.
- Supplier must exist before assignment; import template gains optional `preferred_supplier_code` column resolved on upsert.

### Acceptance Criteria (v4.0)

1. Variant with preferred supplier shows supplier name on product detail page.
2. Auto-reorder draft PO (Phase 5) defaults to preferred supplier when set.
3. Import with invalid `preferred_supplier_code` surfaces row-level error without blocking other rows.
