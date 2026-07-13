# RetailPulse User Manual — Put a Product in Stock (Any Branch)

**Audience:** Store operators, stock clerks, and support teams  
**Version:** 1.0 (June 2026)  
**Scope:** How to make a product **in hand** (available to sell) at a specific branch

This guide is a **focused, step-by-step** walkthrough. For full catalogue and inventory reference, see [`user-manual-inventory-and-catalogue.md`](user-manual-inventory-and-catalogue.md).

---

## Table of contents

1. [What “in hand” means](#1-what-in-hand-means)
2. [Before you start](#2-before-you-start)
3. [Choose the right method](#3-choose-the-right-method)
4. [Method A — Receive stock (recommended)](#4-method-a--receive-stock-recommended)
5. [Method B — Positive stock adjustment](#5-method-b--positive-stock-adjustment)
6. [Method C — Transfer from another branch](#6-method-c--transfer-from-another-branch)
7. [Method D — Opening stock import (bulk go-live)](#7-method-d--opening-stock-import-bulk-go-live)
8. [Method E — Receive from a purchase order](#8-method-e--receive-from-a-purchase-order)
9. [Verify the product is sellable on POS](#9-verify-the-product-is-sellable-on-pos)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. What “in hand” means

In RetailPulse, a product is **in hand** (sellable) at a branch when:

| Term | Meaning |
|------|---------|
| **On hand** | Physical quantity stored in that branch’s warehouse |
| **Available** | On hand minus reserved minus quarantine — what **POS can sell** |
| **In stock (POS)** | Available quantity is greater than zero |

Stock is **not global**. It is stored **per warehouse**, and each warehouse belongs to **one branch**.

> **Important:** Creating a product in **Catalog → Products** does **not** add stock. You must receive, adjust, transfer, or import stock separately (except **Service** and **Digital** products, which do not track inventory).

---

## 2. Before you start

### Step 1 — Confirm the product exists

1. Sign in to the admin panel.
2. Go to **Catalog → Products**.
3. Search for the product by name or SKU.
4. Confirm the product is **Active** and the correct **variant (SKU)** exists.

If the product does not exist, create it first (see Section 5.3 in [`user-manual-inventory-and-catalogue.md`](user-manual-inventory-and-catalogue.md)).

### Step 2 — Confirm the branch and warehouse exist

1. Go to **Organization → Branches** and confirm the target branch exists.
2. Go to **Organization → Warehouses** and confirm there is an **active warehouse** for that branch (usually marked **Default**).

Each branch needs at least one warehouse. POS sells from that branch’s default warehouse unless configured otherwise.

### Step 3 — Check your permissions

You need at least one of:

| Permission | Allows |
|------------|--------|
| `inventory.receive` | Receive stock |
| `inventory.adjust` | Positive adjustment |
| `inventory.transfer` | Inter-warehouse transfer |
| `inventory.import-opening-stock` | Opening stock import |

If a button is missing, ask an administrator to update your role.

### Step 4 — (Optional) Set branch context in the header

For reports and branch-scoped screens, select the correct branch in the **header branch switcher**.  
On **Receive stock**, you pick the warehouse directly — the warehouse label shows **Warehouse name — Branch name**.

---

## 3. Choose the right method

| Situation | Use this method |
|-----------|-----------------|
| Goods arrived at the store (daily replenishment) | **Method A — Receive stock** |
| Small correction or found extra units on shelf | **Method B — Positive adjustment** |
| Stock already exists at another branch/warehouse | **Method C — Transfer** |
| New store go-live with many SKUs at once | **Method D — Opening stock import** |
| Stock arrived on a supplier purchase order | **Method E — PO receive** |

---

## 4. Method A — Receive stock (recommended)

Use this when physical goods arrive at a branch (delivery, internal replenishment, or initial stock for one SKU).

### Steps

1. Go to **Inventory → Stock levels**.
2. Click **Receive stock** (top right).
3. **Warehouse** — Select the warehouse for your branch.  
   - Example: `Downtown Main — Downtown Store`  
   - This is what puts stock **in hand for that branch**.
4. **Product variant** — Search and select the SKU (name or SKU/barcode).
5. **Quantity** — Enter how many units you are receiving (whole number).
6. **Batch number / Expiry date** — Fill in **only if** the product has **Track batches** enabled.
7. **Serial numbers** — Fill in **only if** the product type is **Serialized** (one serial per unit).
8. **Bin location** — Optional. Pick a shelf/bin if you use bin tracking.
9. **Receive to quarantine** — Check **only** if goods need QC before selling.  
   - Quarantined stock is **not** available on POS until released under **Inventory → Quarantine**.
10. **Notes** — Optional (e.g. delivery note reference).
11. Click **Receive stock** to save.

### Example

| Field | Value |
|-------|-------|
| Warehouse | `Downtown Main — Downtown Store` |
| Product | Tapal Danedar Tea 900g (`TEA-TAP-900`) |
| Quantity | `50` |
| Bin | `A-01-003` (optional) |

### Result

- **Stock levels** for that warehouse shows higher **On hand** and **Available**.
- The product appears in **POS search** for that branch (if it was out of stock before).

---

## 5. Method B — Positive stock adjustment

Use this for small corrections when you find extra stock during a count, or when receive is not the right workflow.

### Steps

1. Go to **Inventory → Stock levels**.
2. Click **Adjust stock**.
3. **Warehouse** — Select the branch warehouse where stock should increase.
4. **Product variant** — Search and select the SKU.
5. **Quantity** — Enter a **positive** number (e.g. `5` to add 5 units).
6. **Reason** — Choose **Adjustment** (correction). Use **Damaged** only for write-offs (negative qty).
7. **Batch / expiry** — Required if the product tracks batches.
8. **Notes** — Optional audit note.
9. Submit the form.

### Example

Found 2 extra units on the shelf:

- Warehouse: Downtown Main  
- Product: Samsung Galaxy Buds3 Pro  
- Quantity: `+2`  
- Reason: Adjustment  
- Notes: “Found during shelf tidy”

---

## 6. Method C — Transfer from another branch

Use when stock already exists at another warehouse and you want to move it to the target branch **without** a new purchase.

### Steps

1. Go to **Inventory → Transfers**.
2. Click **New transfer**.
3. **From warehouse** — Source branch warehouse (where stock exists today).
4. **To warehouse** — Destination branch warehouse (where you want it in hand).
5. Add a **line item**: select the variant and quantity to move.
6. Save the transfer (**Draft**).
7. Open the transfer and click **Mark shipped** — stock is deducted at the source.
8. At the destination branch, open the same transfer and click **Confirm received** — enter quantities received.
9. When all lines are fully received, status becomes **Received**.

### Example

Move 10 units from Head Office warehouse to Downtown warehouse:

- From: `HO Main — Head Office`  
- To: `Downtown Main — Downtown Store`  
- Line: 10 × `TEA-TAP-900`  
- Ship → Receive 10 at destination

> **Note:** You cannot ship more than **available** quantity at the source (on hand minus reservations).

---

## 7. Method D — Opening stock import (bulk go-live)

Use when loading **many SKUs** at once for a new branch or initial balances.

### Steps

1. Ensure branches, warehouses, and products (with SKUs) already exist.
2. Go to **Inventory → Stock levels**.
3. Click **Opening stock**.
4. Download the **template** (or use `docs/opening_stock_import.csv` as a reference).
5. Fill one row per warehouse + SKU + quantity:

   | Column | Required | Example |
   |--------|----------|---------|
   | `warehouse_code` | Yes | `MAIN` |
   | `sku` | Yes | `TEA-TAP-900` |
   | `qty` | Yes | `200` |
   | `unit_cost` | Yes | `12.50` |
   | `batch_no` | If batch-tracked | `LOT-2026-01` |
   | `expiry_date` | If batch-tracked | `2027-06-30` |
   | `bin_code` | Optional | `A-01-001` |

6. Upload the file in the import wizard and complete mapping/validation.
7. Run the import (non-strict mode allows partial success with an error report).
8. Verify quantities on **Stock levels** and cost layers (via **Accounting → Financial Reports → Inventory Valuation** or sales COGS behaviour).

> **Important:** `unit_cost` is required. Each successful row creates both stock quantity and an **inventory cost layer** used for COGS on sales. Rows without a valid unit cost are rejected.

> **Tip:** `warehouse_code` must match the code on **Organization → Warehouses** exactly.

---

## 8. Method E — Receive from a purchase order

Use when stock arrives against an approved **Purchase Order** from a supplier.

### Steps

1. Go to **Purchase Orders** and open the relevant PO for the branch.
2. Click **Receive goods** (creates a GRN).
3. Enter quantities received per line (and batch/expiry if required).
4. Post the receipt — stock increases at the PO warehouse for that branch.

For full procurement workflow, see [`phases/phase-10-suppliers-procurement.md`](phases/phase-10-suppliers-procurement.md).

---

## 9. Verify the product is sellable on POS

After putting stock in hand, confirm at the register:

### Step 1 — Check Stock levels

1. Go to **Inventory → Stock levels**.
2. Filter by the branch warehouse.
3. Find the SKU and confirm **Available** is greater than zero.

### Step 2 — Check POS (correct branch)

1. In the admin header, select the **same branch** you stocked.
2. Open **Point of Sale** (Overview → Point of Sale).
3. Search for the product by name, SKU, or scan barcode.
4. The product should **appear in search** and be addable to the cart.

> POS only shows inventory-tracked products that have **available stock** at that branch’s warehouse. Out-of-stock items are hidden from search.

### Step 3 — If the product still does not appear

See [Section 10 — Troubleshooting](#10-troubleshooting).

---

## 10. Troubleshooting

### Product not in POS search but Stock levels shows quantity

| Check | Action |
|-------|--------|
| Wrong branch on POS | Select the correct branch in the header before opening POS |
| Stock in quarantine | **Inventory → Quarantine** → Release |
| Reserved quantity | **Reserved** column on Stock levels — complete or void open POS carts holding stock |
| Service/Digital type | These do not use stock; they should always be sellable if active |
| Inactive product | **Catalog → Products** → ensure **Active** is checked |

### “Batch required” on receive or adjust

The product has **Track batches** enabled. Enter **Batch number** and **Expiry date** on receive/adjust.

### “Insufficient stock” on transfer ship

Source **available** is too low. Receive more stock at source or reduce open cart reservations first.

### Stock went to wrong branch

Stock is tied to the **warehouse** you selected. Receive or transfer again to the correct warehouse, or adjust negatively at the wrong location and positively at the right one (with notes).

### Opening stock import failed

Common causes:

- `warehouse_code` does not match any warehouse  
- `sku` does not exist  
- Duplicate opening balance for same warehouse + SKU + batch — use **adjustment** instead  

---

## Quick reference — one-page checklist

```
□ Product exists and is Active (Catalog → Products)
□ Branch exists (Organization → Branches)
□ Warehouse exists for that branch (Organization → Warehouses)
□ Receive stock OR adjust OR transfer OR import
□ Stock levels → Available > 0 for that warehouse
□ Header branch = target branch
□ POS search finds product and allows add to cart
```

---

## Related documents

| Document | Content |
|----------|---------|
| [`user-manual-inventory-and-catalogue.md`](user-manual-inventory-and-catalogue.md) | Full catalogue & inventory manual |
| [`opening_stock_import.csv`](opening_stock_import.csv) | Sample opening stock spreadsheet |
| [`bulk_adjustment_import.csv`](bulk_adjustment_import.csv) | Sample bulk adjustment file |
| [`products-crud.md`](products-crud.md) | Technical product field reference |

---

## Document history

| Version | Date | Notes |
|---------|------|-------|
| 1.0 | June 2026 | Initial guide — put product in stock for any branch |
