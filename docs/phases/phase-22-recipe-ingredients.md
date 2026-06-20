# Phase 22 — Recipe & Ingredient Management

**SRS Reference:** §3.23
**Status:** Planned
**Depends on:** Phase 5 (Inventory — stock movements), Phase 19 (Restaurant Core — sale completion triggers BOM deduction)
**Feeds into:** Phase 27 (BI — raw material cost data for food cost analytics)

---

## Objective
Enable food-and-beverage businesses to define ingredient bills-of-materials (BOMs) for menu items so that selling a "Cappuccino" automatically deducts espresso, milk, and cup from raw material stock — replacing the need to track finished goods inventory for made-to-order items.

---

## 1. Data Model

### raw_materials
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| branch_id | bigint FK | Branch-specific stock levels |
| name | varchar(150) | "Espresso Beans", "Whole Milk" |
| unit_id | bigint FK | Unit of measure (kg, litre, piece) |
| cost_price | decimal(12,4) | Per unit cost (4 decimal places for accuracy) |
| stock_qty | decimal(12,4) | Current on-hand quantity |
| reorder_point | decimal(12,4) | Threshold for low-stock alert |
| is_active | boolean | |
| created_at / updated_at | timestamps | |

### raw_material_movements
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| raw_material_id | bigint FK | |
| type | enum | `receive`, `consume`, `adjust`, `waste`, `production_consume` |
| quantity | decimal(12,4) | Positive for in, negative for out |
| reference_type | varchar(100) nullable | `App\Models\Sale`, `App\Models\ProductionBatch` |
| reference_id | bigint nullable | |
| notes | text nullable | |
| performed_by | bigint FK → users | |
| created_at | timestamp | |

### recipes
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| product_variant_id | bigint FK | |
| name | varchar(150) | "Standard Cappuccino Recipe" |
| yield_quantity | decimal(10,4) | How many units this recipe produces |
| is_active | boolean | Only one active recipe per variant |
| created_at / updated_at | timestamps | |

### recipe_ingredients
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| recipe_id | bigint FK | |
| raw_material_id | bigint FK | |
| quantity | decimal(12,4) | Per yield_quantity |
| wastage_percent | decimal(5,2) | Added on top of quantity |

### production_batches
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| branch_id | bigint FK | |
| product_variant_id | bigint FK | Finished good produced |
| quantity_produced | decimal(12,4) | |
| recipe_id | bigint FK | Recipe used |
| produced_by | bigint FK → users | |
| produced_at | timestamp | |
| notes | text nullable | |

---

## 2. BOM Service

`BOMService::deductForSale(Sale $sale): void`

```
for each SaleItem in sale:
    variant = saleItem.productVariant
    recipe  = Recipe::activeFor(variant)
    if recipe is null: skip (finished-goods variant; use regular stock movement)

    effectiveQty = saleItem.quantity / recipe.yield_quantity
    for each RecipeIngredient in recipe:
        deductQty = (ingredient.quantity * effectiveQty)
                  * (1 + ingredient.wastage_percent / 100)
        RawMaterial::decrement(deductQty)
        RawMaterialMovement::record(type=consume, qty=-deductQty, ref=sale)

    if rawMaterial.stock_qty <= rawMaterial.reorder_point:
        dispatch(RawMaterialLowStockEvent)
```

- Called from `SaleCompletedEvent` listener, within the same DB transaction as the sale.
- If a raw material has insufficient stock, behaviour is controlled by config:
    - `restaurant.bom_insufficient_stock = warn` (default) — sale proceeds, admin alerted.
    - `restaurant.bom_insufficient_stock = block` — sale blocked until stock is available.

---

## 3. Production Batches

For pre-made items (e.g., pre-baked pastries):
1. Operator opens Admin → Production → New Batch.
2. Selects a variant with a recipe, enters quantity to produce.
3. `ProductionBatchService::record()`:
    - Creates `production_batches` record.
    - Deducts raw materials via `RawMaterialMovement` (type = `production_consume`).
    - Adds finished goods to product inventory via `StockMovement` (type = `production`).

---

## 4. Low Stock Alert

`RawMaterialLowStockEvent` dispatched when `stock_qty <= reorder_point` after any deduction.

Listener: `SendRawMaterialLowStockNotification` — sends an in-app and email notification to the branch manager listing the material, current qty, reorder point, and estimated servings remaining.

---

## 5. Admin UI

- **Production → Raw Materials:** CRUD with current stock level, cost price, reorder point; quick "Receive Stock" button (creates `receive` movement).
- **Production → Recipes:** Create/edit recipe per variant; ingredient list with quantity and wastage %; "Test Deduction" preview shows ingredient quantities for N servings.
- **Production → Production Batches:** Log and history of production runs; raw material consumption summary.
- **Production → Raw Material Report:** Movements log (filterable by material, date, type); current stock valuation.

---

## 6. API Endpoints

| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| GET | /admin/production/raw-materials | production.view | List raw materials |
| POST | /admin/production/raw-materials | production.manage | Create raw material |
| PATCH | /admin/production/raw-materials/{id}/receive | production.manage | Receive stock |
| GET | /admin/production/recipes | production.view | List recipes |
| POST | /admin/production/recipes | production.manage | Create recipe |
| POST | /admin/production/batches | production.manage | Log production batch |
| GET | /admin/production/batches | production.view | Production history |

---

## 7. Services & Classes

- `BOMService` — resolves ingredient deduction for a sale or production batch.
- `RawMaterialService` — CRUD, stock receive, adjustment.
- `RecipeService` — create/update/clone recipes; ensure only one active recipe per variant.
- `ProductionBatchService` — log batch, deduct materials, add finished goods.
- `RawMaterialLowStockEvent` / `SendRawMaterialLowStockNotification` — alert pipeline.
