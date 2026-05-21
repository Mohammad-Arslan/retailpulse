# Products CRUD Reference

This document describes how products are defined in RetailPulse: the six product types, which fields apply to each type, and what is required when creating or editing a product in the admin UI.

**Source of truth in code**

| Concern | Location |
|--------|----------|
| Product types enum | `app/Enums/ProductType.php` |
| HTTP validation (create) | `app/Http/Requests/Admin/StoreProductRequest.php` |
| HTTP validation (update) | `app/Http/Requests/Admin/UpdateProductRequest.php` |
| Business rules | `app/Services/ProductService.php` |
| Admin form UI | `resources/js/Components/admin/ProductFormFields.jsx` |
| Spreadsheet import columns | `app/Services/ImportExport/Handlers/ProductImportHandler.php` |
| Phase spec | `docs/phases/phase-04-product-information.md` |

---

## Product types (6)

RetailPulse supports **six** product types. The value is stored in `products.type` as a string (not a MySQL `ENUM`).

| Type | Label (UI) | Variants | Tracks inventory | Serial tracking | Bundle lines |
|------|------------|----------|------------------|-----------------|--------------|
| `standard` | Standard | 1 | Yes | No | No |
| `variable` | Variable | Many (attribute matrix) | Yes | No | No |
| `service` | Service | 1 | **No** | No | No |
| `digital` | Digital | 1 | **No** | No | No |
| `serialized` | Serialized | 1 | Yes | **Yes** (auto on create) | No |
| `combo` | Combo / Bundle | 1 parent | Yes | No | **Yes** |

### Type behaviour (automatic)

These flags are set by the backend based on type — they are not separate form fields:

- **`track_serials`** — automatically `true` when type is `serialized`; serial numbers are captured later during **stock receive**, not on the product form.
- **Inventory tracking** — disabled for `service` and `digital` (`Product::tracksInventory()`).
- **Product type is immutable** — chosen on create only; the edit form does not allow changing type.

---

## Field model overview

Products use two layers:

1. **Product** — shared catalog data (`products` table): name, type, category, flags, etc.
2. **Variant(s)** — sellable SKU (`product_variants` table): prices, barcode, reorder point, attribute JSON.

Most “simple” types expose one default variant. **Variable** products generate one variant per combination of attributes (e.g. Size × Color).

---

## Shared fields (all types)

These appear in the **General** section for every product type.

| Field | Create | Update | Required | Notes |
|-------|--------|--------|----------|-------|
| **Type** | Yes | No (hidden) | **Yes** | One of the six types above |
| **Name** | Yes | Yes | **Yes** | Max 255 characters |
| **Description** | Yes | Yes | Optional | Max 10,000 characters |
| **Category** | Yes | Yes | Optional | FK to `categories` |
| **Brand** | Yes | Yes | Optional | FK to `brands` |
| **Unit** | Yes | Yes | Optional | FK to `units` |
| **Track batches** | Yes | Yes | Optional | Checkbox; enables batch/expiry on stock receive |
| **Active** | Yes | Yes | Optional | Defaults to active |

**Auto-generated (not on form):** `slug` (from name), tenant scope.

**In database but not in CRUD UI yet:** `tax_group_id` (planned for Phase 14).

---

## Variant fields

Each variant row (`product_variants`) can contain:

| Field | Required (HTTP) | Notes |
|-------|-----------------|-------|
| **SKU** | Optional | Auto-generated (`RP-` prefix) if omitted |
| **Barcode** | Optional | Auto-generated (EAN-13) if omitted |
| **Variant name / label** | Optional | Display name; defaults to product name or matrix label |
| **Cost price** | Optional | Hidden unless user has `products.show-cost` |
| **Sell price** | Optional | Defaults to `0` |
| **Reorder point** | Optional | Low-stock threshold |
| **Attributes** | Optional | JSON map, e.g. `{"Size":"M"}` — used by **variable** products |

---

## Fields by product type

Legend: **Required** · Optional · **Effective required** (enforced by UI or `ProductService`, not always by FormRequest) · N/A

### 1. Standard (`standard`)

Default physical product with a single SKU.

| Section | Field | Create | Update | Required |
|---------|-------|--------|--------|----------|
| General | All shared fields | Yes | Yes | Name + type (create only) |
| Pricing | Cost price | Yes | Via variant sync | Optional |
| Pricing | Sell price | Yes | Via variant sync | Optional |
| Pricing | Reorder point | Yes | Via variant sync | Optional |
| Branch pricing | Per-branch sell override | Yes | Yes | Optional |
| Variants | Single default variant | Auto | Editable via sync | SKU/barcode auto if empty |

**Inventory:** tracked.

---

### 2. Variable (`variable`)

Multiple variants from attribute combinations (e.g. Size, Color).

| Section | Field | Create | Update | Required |
|---------|-------|--------|--------|----------|
| General | All shared fields | Yes | Yes | Name |
| Attributes | `variant_attributes` (name + options[]) | Yes | Yes | **Effective required** — at least one attribute with ≥1 option; service throws if matrix is empty |
| Attributes | Regenerate variants | No | Yes (checkbox) | Optional — rebuilds all variants from attributes |
| Pricing | Default cost / sell / reorder | Yes (create) | N/A on create card | Optional — applied as defaults when variants are generated |
| Variants | Generated variant rows | Auto (matrix) | Price table OR regenerate | One row per attribute combination |
| Branch pricing | Per-branch overrides | N/A | N/A | **Not shown in UI** for variable products |

**On create:** `VariantMatrix` builds all combinations; each gets SKU/barcode if not supplied.

**On update (without regenerate):** only existing variant **prices**, **cost**, and **reorder point** can be edited — not the attribute matrix structure.

**On update (with regenerate):** all variants are deleted and recreated from the current attribute set.

---

### 3. Service (`service`)

Non-stock service item (e.g. installation, consultation).

| Section | Field | Create | Update | Required |
|---------|-------|--------|--------|----------|
| General | All shared fields | Yes | Yes | Name |
| Pricing | Cost / sell / reorder | Yes | Via variant sync | Optional |
| Branch pricing | Per-branch sell override | Yes | Yes | Optional |

**Inventory:** **not** tracked — stock operations do not apply.

Same form layout as **standard**; no extra type-specific sections.

---

### 4. Digital (`digital`)

Digital goods (e.g. license, download) — same CRUD shape as service.

| Section | Field | Create | Update | Required |
|---------|-------|--------|--------|----------|
| General | All shared fields | Yes | Yes | Name |
| Pricing | Cost / sell / reorder | Yes | Via variant sync | Optional |
| Branch pricing | Per-branch sell override | Yes | Yes | Optional |

**Inventory:** **not** tracked.

---

### 5. Serialized (`serialized`)

Physical items tracked by unique serial number (e.g. electronics, appliances).

| Section | Field | Create | Update | Required |
|---------|-------|--------|--------|----------|
| General | All shared fields | Yes | Yes | Name |
| General | Serial tracking hint | Shown | Shown | Informational only |
| Pricing | Cost / sell / reorder | Yes | Via variant sync | Optional |
| Branch pricing | Per-branch sell override | Yes | Yes | Optional |
| Serial numbers | Capture on product form | N/A | N/A | Serials entered on **inventory receive**, not here |

**`track_serials`:** set automatically to `true` on create.

**Inventory:** tracked; receive stock requires one serial per unit.

---

### 6. Combo / Bundle (`combo`)

A bundle SKU made of other product variants.

| Section | Field | Create | Update | Required |
|---------|-------|--------|--------|----------|
| General | All shared fields | Yes | Yes | Name |
| Bundle | `bundle_items[]` | Yes | Yes | **Effective required** for a usable bundle — each line needs **child variant** + **quantity** |
| Bundle | `child_variant_id` | Per line | Per line | **Required** when a bundle line is submitted |
| Bundle | `quantity` | Per line | Per line | **Required**, min `0.0001` |
| Pricing | Cost / sell / reorder (parent) | Yes | Limited | Optional — one parent variant |
| Branch pricing | Per-branch sell override | Yes | Yes | Optional |

**Rules:**

- Bundle cannot include its own variant (`ProductService::syncBundleItems`).
- Combo builder search excludes other `combo` products and the current product.
- On update, variant sync is skipped for combo — only bundle lines and branch prices change.

**Inventory:** tracked on the parent variant; POS resolves child lines from `product_bundle_items`.

---

## Branch pricing (optional, multi-branch)

When the tenant has branches, **standard**, **service**, **digital**, **serialized**, and **combo** products show a **Branch pricing** section on create and edit.

| Field | Required when row present |
|-------|---------------------------|
| `branch_id` | **Yes** |
| `sell_price` | **Yes**, ≥ 0 |

Leave blank to use the default variant sell price at that branch.

**Not available** for **variable** products in the current UI.

---

## Create vs update summary

| Aspect | Create | Update |
|--------|--------|--------|
| Product type | Selectable | Fixed (not submitted) |
| Default pricing fields | Shown for simple + combo + variable (create) | Not used (`default_*` absent from update request) |
| Variable regenerate | N/A | Optional checkbox |
| SKU/barcode uniqueness | Enforced on create | Not re-validated as unique on update |
| Variant IDs | N/A | Optional `variants.*.id` for existing rows |

---

## Spreadsheet import (flat row model)

Import uses **one row = one product with one variant**. It does **not** fully replicate the admin CRUD for variable matrices or combo bundles.

| Column | Required | Default if empty |
|--------|----------|------------------|
| Product Name | **Yes** | — |
| SKU | **Yes** | — |
| Category Code | **Yes** | Must exist in `categories.slug` |
| Sell Price | **Yes** | — |
| Brand Code | Optional | — |
| Unit Name | Optional | — |
| Barcode | Optional | — |
| Cost Price | Optional | — |
| Product Type | Optional | `standard` |
| Variant Label | Optional | — |
| Active | Optional | — |

Allowed **Product Type** values: `standard`, `variable`, `service`, `digital`, `serialized`, `combo`.

**Import limitations:** setting `type` to `variable` or `combo` does not create attribute matrices or bundle lines — use the admin UI for those.

---

## Quick decision guide

```
Need one SKU, physical stock?        → standard
Need Size/Color/etc. variants?       → variable
No stock (labour / fee)?             → service
No stock (digital delivery)?         → digital
Track each unit by serial?           → serialized
Sell a kit of other products?        → combo
```

---

## Permissions

| Permission | Purpose |
|------------|---------|
| `products.view` | List products |
| `products.create` | Create |
| `products.update` | Edit |
| `products.delete` | Delete |
| `products.show-cost` | See/edit cost price |
| `products.import` | Import gateway |
| `products.export` | Export catalog |

---

## Related tables (reference)

| Table | Purpose |
|-------|---------|
| `products` | Master product record |
| `product_variants` | SKUs, prices, attributes |
| `product_bundle_items` | Combo child lines |
| `product_batches` | Batch numbers & expiry |
| `product_serials` | Serial numbers per variant |
| `branch_product_prices` | Branch-specific sell price overrides |

For inventory behaviour (receive, transfer, serial capture), see `docs/phases/phase-05-inventory-warehouse.md`.
