# RetailPulse User Manual — Catalogue & Inventory

**Audience:** Customer support teams and store operators  
**Version:** 1.4 (July 2026)  
**Scope:** Product catalogue (PIM) and warehouse inventory features available in the admin panel

This manual explains **where to click**, **what each screen does**, **how data flows**, and **what every term means**. Hand it to customers who manage products and stock in RetailPulse.

**See also:** [`user-manual-put-product-in-stock.md`](user-manual-put-product-in-stock.md) — focused step-by-step guide for putting a product **in hand** at any branch (receive, adjust, transfer, import, POS verify).

---

## Table of contents

1. [Before you start](#1-before-you-start)
2. [Glossary — terms and abbreviations](#2-glossary--terms-and-abbreviations)
3. [Admin navigation map](#3-admin-navigation-map)
4. [Organisation setup (branches & warehouses)](#4-organisation-setup-branches--warehouses)
5. [Catalogue setup](#5-catalogue-setup)
6. [Inventory operations](#6-inventory-operations)
7. [End-to-end flow examples](#7-end-to-end-flow-examples)
8. [Permissions reference (for support)](#8-permissions-reference-for-support)
9. [Import & export](#9-import--export)
10. [Troubleshooting & FAQ](#10-troubleshooting--faq)

---

## 1. Before you start

### 1.1 What this manual covers

| Area | What you can do |
|------|-----------------|
| **Catalogue** | Categories, brands, units, products (6 product types), images, branch pricing, bulk import/export |
| **Inventory** | Stock levels, receive, adjust, transfers, bins/zones, quarantine, cycle counts, reorder settings |

**Not covered here:** Point of Sale checkout, suppliers/GRN, accounting, and mobile scanner apps (separate manuals).

### 1.2 Logging in

1. Open your RetailPulse URL (e.g. `https://your-store.example/admin`).
2. Sign in with the email and password provided by your administrator.
3. After login:
   - Users with **Dashboard** permissions land on the **ERP Home Dashboard** (business KPIs and exceptions — not user/role counts).
   - Users with only **POS Access** (cashiers) land on the **Point of Sale** full-screen register (no admin sidebar). Checkout (confirm sale + payment) uses the same shell.

Home destination is resolved from permissions only. Creating a new role and assigning permissions will automatically choose the correct landing page.

### 1.2.1 ERP Home Dashboard widgets

The Dashboard shows only widgets you are allowed to see. Assign these permissions on the role:

| Permission | Widget |
|------------|--------|
| `dashboard.exceptions.view` | Health strip summary + Needs Attention feed |
| `dashboard.sales.view` | Sales KPIs (with day-over-day trend badges when data exists) |
| `dashboard.view-profit` | Gross profit KPI and revenue bar chart (7-day / 6-month toggle) |
| `dashboard.inventory.view` | Inventory operations card, stock movement trend |
| `dashboard.procurement.view` | Procurement operations card + top suppliers |
| `dashboard.finance.view` | Finance operations card (unposted journals, bank match queue, AR/AP aging) |
| `dashboard.operations.view` | Branch / warehouse / catalogue snapshot |

Layout (top to bottom): greeting → health strip → Sales KPIs → revenue chart beside Needs Attention → Operations (Inventory / Procurement / Finance) → Organization snapshot → Quick Actions.

**Global Search (Ctrl/Cmd+K or top navbar):** Opens from the top bar search field. Results are grouped (Pages, Products, Customers, Sales, Purchasing, Accounting, etc.) and only include records you have permission to see, scoped to the active branch where applicable.

**Sidebar Search Pages:** The search field under the logo jumps only between navigation pages (same sidebar menu), not products or other records.

IAM statistics (users, roles, permissions charts) are **not** on the home dashboard. Manage those under **Organization**.

### 1.3 Branch context (important)

RetailPulse is **multi-branch**. Many screens filter data by the **active branch** shown in the header branch switcher.

- If the user sees “Select a branch from the header switcher…”, they must pick a branch first.
- Warehouses belong to a branch. Stock is tracked **per warehouse**, not globally.

### 1.4 Roles and permissions

Menu items appear only if the user’s role has the required permission. If a customer says “I don’t see Transfers”, check their role in **Admin → Users → Roles** (see [Section 8](#8-permissions-reference-for-support)).

---

## 2. Glossary — terms and abbreviations

### 2.1 Organisation

| Term | Meaning |
|------|---------|
| **Branch** | A physical store or business location. Users, warehouses, and stock settings can be scoped to a branch. |
| **Warehouse** | A storage location **within a branch** where stock is held. Each branch has at least one warehouse (often a “default” warehouse). |
| **Default warehouse** | The primary warehouse for a branch. Used when no other warehouse is specified. |

### 2.2 Catalogue (product master data)

| Term | Meaning |
|------|---------|
| **Catalogue / PIM** | Product Information Management — the master list of everything you sell. |
| **Product** | The shared product record: name, type, category, description, flags. |
| **Variant** | A sellable **SKU** (Stock Keeping Unit). One product can have one or many variants. |
| **SKU** | Unique stock code for a variant (e.g. `RP-000123`). Used in reports, imports, and barcode labels. |
| **Barcode** | Scannable identifier (often EAN-13). Auto-generated if left blank. |
| **Category** | Grouping for products (e.g. “Beverages → Soft Drinks”). Supports parent/child hierarchy. |
| **Brand** | Manufacturer or label (e.g. “Acme Foods”). |
| **Unit / UoM** | Unit of measure (each, kg, liter, box). |
| **Standard product** | Single-SKU physical item. Most common type. |
| **Variable product** | Multiple SKUs from attributes (Size, Color, etc.). |
| **Service** | Non-stock item (labour, fee). No inventory tracking. |
| **Digital** | Non-stock digital goods. No inventory tracking. |
| **Serialized product** | Each unit has a unique **serial number** captured at receive time. |
| **Combo / Bundle** | One sellable SKU made of other product variants (kit). |
| **Branch pricing** | Optional sell price override for a specific branch (not available for variable products in current UI). |
| **Reorder point** | When on-hand stock falls to this level, the system can flag low stock (see Branch stock settings). |
| **Preferred supplier** | Primary supplier linked to a variant (used for future auto-reorder). |

### 2.3 Inventory & warehouse

| Term | Meaning |
|------|---------|
| **On hand** | Physical quantity in the warehouse (sellable + reserved + quarantine breakdown). |
| **Reserved** | Quantity held for open POS carts or other reservations — **not available** for new sales. |
| **Available** | `On hand − Reserved − Quarantine` (sellable quantity). |
| **Quarantine** | Stock received but **not yet approved for sale** (pending QC). Excluded from POS availability. |
| **Batch** | Lot number for expiry-tracked products. Required when “Track batches” is enabled on the product. |
| **Expiry date** | Best-before / use-by date tied to a batch. |
| **Serial number** | Unique ID per unit for serialized products. |
| **Stock movement** | Immutable ledger entry every time quantity changes (receive, sale, adjustment, transfer, etc.). |
| **Receive** | Increase stock (like a goods-in / purchase receive). |
| **Adjustment** | Manual correction (+ or −) with reason `adjustment` or `damaged`. |
| **Opening stock** | Initial balances loaded at go-live via import — sets on-hand directly. |
| **Transfer** | Move stock **between two warehouses** (ship → receive workflow). |
| **Bin / bin location** | Physical shelf slot inside a warehouse (zone + aisle + shelf + bin code). |
| **Zone** | Logical area inside a warehouse (e.g. “Cold storage”, “Aisle A”). |
| **Bin transfer** | Move stock **between bins in the same warehouse** (does not change warehouse total incorrectly). |
| **FEFO** | First Expiry, First Out — batch picking prefers earliest expiry. |
| **FIFO** | First In, First Out — oldest stock picked first. |
| **Cycle count / stocktake** | Physical count session compared to system quantity; variances posted as adjustments. |
| **Blind count** | Counters do not see system quantity until they submit counts. |
| **Freeze mode** | During an active count in scope, stock movements in that scope are blocked until the count is posted. |
| **Safety stock** | Minimum buffer quantity per branch (branch-level override). |
| **Cutover date** | Branch go-live date; before this date POS sales cannot deduct stock (manual inventory still works). |

### 2.4 Abbreviations

| Abbr. | Full form |
|-------|-----------|
| **PIM** | Product Information Management |
| **SKU** | Stock Keeping Unit |
| **UoM** | Unit of Measure |
| **POS** | Point of Sale (cash register screen) |
| **QC** | Quality Control |
| **GRN** | Goods Received Note (supplier delivery — future procurement phase) |
| **PO** | Purchase Order |
| **RBAC** | Role-Based Access Control (permissions system) |
| **CSV** | Comma-separated values (spreadsheet file) |
| **TTL** | Time To Live (e.g. cart reservation expires after N minutes) |

---

## 3. Admin navigation map

Sidebar sections relevant to catalogue and inventory:

```
Overview
  └── Dashboard
  └── Point of Sale

Organization
  └── Branches
  └── Warehouses

Inventory
  └── Stock levels          ← main stock report
  └── Transfers             ← inter-warehouse moves
  └── Bin stock             ← report by bin
  └── Bin transfer          ← move within same warehouse
  └── Branch stock settings ← reorder / safety stock per branch
  └── Quarantine            ← release or scrap QC hold stock
  └── Cycle counts          ← physical count sessions
  └── Count schedules       ← recurring auto-created counts

Catalog
  └── Products
  └── Categories
  └── Brands
  └── Units
```

### 3.1 Hidden but important: Manage bins

**Zones and bin locations are not a top-level menu item.** Path:

**Organization → Warehouses → Edit (a warehouse) → Manage bins**

URL pattern: `/admin/warehouses/{id}/bins`

Requires permission: `inventory.manage-bins`

---

## 4. Organisation setup (branches & warehouses)

### 4.1 Recommended setup order

```
1. Create branch(es)          → Organization → Branches
2. Create warehouse(es)       → Organization → Warehouses
3. Define zones & bins        → Warehouses → Edit → Manage bins
4. Build catalogue            → Catalog → Categories, Brands, Units, Products
5. Load opening stock         → Stock levels → Opening stock import
   OR receive manually        → Stock levels → Receive stock
6. Configure reorder levels   → Branch stock settings (optional)
```

### 4.2 Branches

**Path:** Organization → Branches

| Action | Steps |
|--------|-------|
| View branches | Open Branches list |
| Create branch | Click **Add branch**, fill name/code, save |
| Edit branch | Row action **Edit** — update name, address, **cutover date** |

**Cutover date:** If set to a future date/time, POS cannot deduct inventory until that moment. Manual receive, adjust, and transfers still work. Use this for go-live planning.

### 4.3 Warehouses

**Path:** Organization → Warehouses

Each warehouse belongs to **one branch**. Warehouse **code** is auto-generated at creation and cannot be changed.

| Field | Purpose |
|-------|---------|
| Name | Display name (e.g. “Main store back room”) |
| Code | Unique short code used in imports (e.g. `WH-001`) |
| Default | Checkbox — default warehouse for that branch |

**Deactivate:** Inactive warehouses disappear from pickers but historical stock rows remain.

### 4.4 Zones and bin locations

**Path:** Organization → Warehouses → **Edit** → **Manage bins**

Two panels:

**Left — Zones**

1. Click **Add zone**.
2. Enter **Name** (e.g. “Ambient”) and **Code** (e.g. `AMB`).
3. Click **Save zone**.
4. Use **Edit zone** on a row to rename, change code, or deactivate.

**Right — Bin locations**

1. Click **Add bin**.
2. Optionally pick a **Zone**.
3. Enter **Aisle**, **Shelf**, and **Bin code** (required unique identifier within warehouse).
4. Click **Save bin**.

**Example bin layout**

| Zone | Aisle | Shelf | Bin code | Example use |
|------|-------|-------|----------|-------------|
| Ambient | A | 1 | A-01-001 | Dry goods shelf 1 |
| Cold | C | 2 | C-02-010 | Refrigerated bay |

Bins appear when **receiving stock** and in **bin transfer** / **bin stock report**.

---

## 5. Catalogue setup

### 5.1 Master data first

Before creating products, set up supporting lists (optional but recommended):

| Screen | Path | Example |
|--------|------|---------|
| Categories | Catalog → Categories | “Electronics”, “Accessories” |
| Brands | Catalog → Brands | “Samsung”, “Generic” |
| Units | Catalog → Units | “Each”, “Kilogram”, “Liter” |

**Category hierarchy:** When creating a category, choose a **Parent category** or leave as top level.

### 5.2 Product types — quick chooser

| Customer need | Choose type |
|---------------|-------------|
| One barcode, tracks stock | **Standard** |
| Size/Color variants | **Variable** |
| Installation fee, no stock | **Service** |
| Download/license, no stock | **Digital** |
| Laptops with unique serial each | **Serialized** |
| Gift basket of other items | **Combo / Bundle** |

**Important:** Product type is chosen **only at creation** and cannot be changed later.

### 5.3 Creating a standard product (step by step)

**Path:** Catalog → Products → **Add product**

1. **Type:** Standard  
2. **General:** Name (required), description, category, brand, unit  
3. **Track batches:** Enable if product uses lot numbers and expiry (e.g. food, pharma)  
4. **Active:** Leave checked to show in POS/catalog  
5. **Pricing:** Cost price (if permitted), sell price, reorder point  
6. **Branch pricing:** Optional per-branch sell overrides  
7. **Images:** Upload up to system limit (JPEG/PNG/WebP)  
8. Click **Create product**

**Auto identifiers:** SKU (`RP-…`) and barcode (EAN-13) generate automatically unless overridden.

**Example — Standard product**

| Field | Value |
|-------|-------|
| Name | Organic Olive Oil 500ml |
| Category | Grocery → Oils |
| Unit | Each |
| Sell price | 12.99 |
| Reorder point | 24 |
| Track batches | Yes (batch + expiry required on receive) |

### 5.4 Creating a variable product (step by step)

**Path:** Catalog → Products → Add product → Type: **Variable**

1. Fill general fields (name, category, etc.).  
2. **Attributes section:** Add attribute names and options.  
   - Example: Attribute **Size** → options `S`, `M`, `L`  
   - Example: Attribute **Color** → options `Red`, `Blue`  
3. System generates **one variant per combination** (3 sizes × 2 colors = 6 SKUs).  
4. Set **default prices** for new combinations, then fine-tune each row in the **variant pricing matrix**.  
5. Save.

**Editing variable products later**

| Goal | Action |
|------|--------|
| Change prices only | Edit product → update matrix → Save |
| Change attribute structure (add Size XL) | Edit → change attributes → check **Regenerate all variants** → Save |

**Warning:** Regenerate **deletes and recreates** all variants. Use only when attribute structure changes.

**Example — Variable product**

Product: **Cotton T-Shirt**  
Attributes: Size (S, M, L), Color (Black, White) → **6 variants**, each with own SKU and price.

### 5.5 Service and digital products

Same form as standard, but **inventory is not tracked**. You can sell them on POS without receiving stock.

Use cases:

- **Service:** “TV wall mounting”  
- **Digital:** “E-book download code”

### 5.6 Serialized products

1. Create product with type **Serialized**.  
2. Set pricing as usual.  
3. **Do not enter serial numbers on the product form.**  
4. When stock arrives, go to **Receive stock** and enter **one serial number per unit** (see [Section 6.3](#63-receive-stock)).

### 5.7 Combo / bundle products

**Path:** Catalog → Products → Add product → Type: **Combo**

1. Fill general fields.  
2. **Bundle section:** Search and add **component variants** with quantities.  
   - Example: “Breakfast Kit” = 2× Coffee variant + 1× Mug variant  
3. Set parent sell price (or branch overrides).  
4. Save.

**Rules:**

- A bundle cannot include itself.  
- Component products must already exist as variants.  
- Parent SKU tracks inventory; POS expands to child lines at checkout.

### 5.8 Viewing and editing products

**Path:** Catalog → Products → click row or **Edit**

Product detail/show page displays:

- Variants, SKUs, barcodes  
- Preferred supplier (if set)  
- Branch prices  
- Images  

Bulk actions (if permitted): deactivate, delete via catalog bulk tools.

### 5.9 Deactivating vs deleting

| Action | Effect |
|--------|--------|
| **Deactivate (Active unchecked)** | Hidden from new POS picks; history retained |
| **Delete** | Removes product (only when permitted and safe) |

Prefer deactivation for seasonal items.

---

## 6. Inventory operations

### 6.1 Stock levels (main inventory screen)

**Path:** Inventory → **Stock levels**

Shows real-time quantities per variant (and batch if applicable):

| Column | Meaning |
|--------|--------|
| On hand | Total physical qty |
| Reserved | Qty held for open carts |
| Available | Qty sellable now |
| Bin | Bin location if assigned |
| Quarantine | Qty not yet released for sale |

**Actions on this page (header buttons):**

| Button | Permission | Purpose |
|--------|------------|---------|
| Receive stock | `inventory.receive` | Add stock |
| Adjust stock | `inventory.adjust` | Correct or write off |
| Transfers | `inventory.transfer` | Link to transfer list |
| Opening stock | `inventory.import-opening-stock` | Bulk go-live import |
| Bulk adjustments | `inventory.bulk-adjustment-import` | Spreadsheet corrections |

Filter by warehouse and search by SKU/product name.

### 6.2 Stock adjustment

**Path:** Stock levels → **Adjust stock**

Use when counts are wrong or goods are damaged/written off.

| Field | Guidance |
|-------|----------|
| Warehouse | Where stock lives |
| Product variant | Search by name/SKU |
| Quantity | **Positive** adds, **negative** removes |
| Reason | `adjustment` (correction) or `damaged` (write-off) |
| Batch / expiry | Required if product tracks batches |

**Example — Damaged goods**

- Warehouse: Main Store WH  
- Product: Glass Jar 500ml  
- Quantity: **−3**  
- Reason: **Damaged**  
- Notes: “Broken in transit”  

Result: On hand decreases by 3; movement recorded permanently.

### 6.3 Receive stock

**Path:** Stock levels → **Receive stock**

Use when goods arrive (manual receive — full GRN workflow is a future procurement feature).

| Field | Guidance |
|-------|----------|
| Warehouse | Destination |
| Variant | Product being received |
| Quantity | Units received |
| Bin location | Optional — assign to shelf |
| Batch / expiry | Required for batch-tracked products |
| Serial numbers | One field per unit for serialized products |
| Receive to quarantine | Check if QC required before selling |

**Example A — Simple receive**

100 units of SKU `RP-000501` into Default Warehouse, no bin.

**Example B — Batch receive**

Product tracks batches: Batch `LOT-2026-001`, Expiry `2027-06-30`, Qty 50.

**Example C — Quarantine receive**

Check **Receive to quarantine**. Stock appears in **Quarantine** column, not **Available**. After QC, release from **Inventory → Quarantine**.

### 6.4 Quarantine

**Path:** Inventory → **Quarantine**

Lists stock pending QC.

| Action | Result |
|--------|--------|
| **Release** | Moves quantity from quarantine → sellable on hand |
| **Scrap** | Writes off quarantined quantity (cannot undo) |

Permission: `inventory.release-quarantine`

### 6.5 Stock transfers (between warehouses)

**Path:** Inventory → **Transfers**

**Lifecycle:**

```
Draft → Shipped → Partially received → Received
```

| Step | Who | What happens |
|------|-----|--------------|
| 1. Create | User with `inventory.transfer` | Pick **from** and **to** warehouse, add line items + quantities |
| 2. Ship | Same | Click **Mark shipped** — stock **deducted** at source |
| 3. Receive | User at destination | Open transfer → **Confirm received** — enter qty received (can be partial) |
| 4. Complete | System | When all lines fully received → status **Received** |

**Example**

- From: **Warehouse A (Branch 1)**  
- To: **Warehouse B (Branch 2)**  
- Lines: 20× Widget SKU, 10× Gadget SKU  
- Ship 20+10 from A → receive at B (partial receive supported if only 15 widgets arrive)

**Note:** Cannot ship more than **available** (on hand minus reserved) at source.

### 6.6 Bin transfer (within one warehouse)

**Path:** Inventory → **Bin transfer**

Moves quantity from one bin to another **in the same warehouse**. Warehouse total unchanged.

**Example:** Move 12 units from bin `A-01-001` to `A-02-005`.

Permission: `inventory.manage-bins`

### 6.7 Bin stock report

**Path:** Inventory → **Bin stock**

Read-only report: current quantity by bin, filterable by warehouse/zone.

Permission: `inventory.reports`

### 6.8 Branch stock settings

**Path:** Inventory → **Branch stock settings**

**Requires active branch** in header switcher.

Per variant, override for **this branch only**:

| Field | Purpose |
|-------|---------|
| Default reorder | From product variant master |
| Branch reorder | Branch-specific reorder point |
| Safety stock | Minimum buffer before low-stock alerts |

**Example:** Master reorder = 50, Branch reorder = 20 for a small outlet.

Permission: `inventory.adjust`

### 6.9 Cycle counts (physical stocktake)

**Path:** Inventory → **Cycle counts**

#### 6.9.1 Count session workflow

```
Create session (Draft)
    → Start count (In progress)
        → Counters enter counted quantities
        → Submit counts
    → Under review (variances calculated)
        → Manager Approve (if thresholds exceeded, needs approver permission)
    → Approved
        → Post adjustments (Posted)
            → System creates cycle_count_adjustment movements
```

#### 6.9.2 Creating a count session

**Path:** Cycle counts → **New session**

| Field | Options |
|-------|---------|
| Branch | Active branch |
| Warehouse | Which warehouse to count |
| Scope | **Full warehouse**, **Zone**, or **Category** |
| Blind count | Hide system qty from counters |
| Freeze mode | Block stock moves in scope until posted |
| Variance thresholds | % and/or value — large variances need approval |

#### 6.9.3 Running the count

1. Open session from list.  
2. Click **Start count**.  
3. For each line, enter **Counted qty** (system qty shown unless blind mode).  
4. Click **Submit counts**.  
5. Manager with `inventory.cycle-count.approve` clicks **Approve variances** if required.  
6. Click **Post adjustments** to update ledger.

**Example — Zone count**

- Warehouse: Main WH  
- Scope: Zone “Cold storage”  
- Freeze mode: On (no picks from cold zone during count)  
- Blind count: On (staff count without seeing system figures)

### 6.10 Count schedules (recurring counts)

**Path:** Inventory → **Count schedules**

Automated rules that create **draft** count sessions on a schedule (processed nightly).

| Field | Example |
|-------|---------|
| Frequency | Weekly |
| Day of week | Monday |
| Scope | Full warehouse or zone/category |
| Blind / Freeze | Same as manual sessions |

Edit or deactivate schedules from the list.

---

## 7. End-to-end flow examples

### 7.1 New store go-live (full setup)

**Scenario:** Open a new branch “Downtown” with 200 SKUs.

| Step | Action | Screen |
|------|--------|--------|
| 1 | Create branch “Downtown” | Branches |
| 2 | Create warehouse “Downtown Main” (default) | Warehouses |
| 3 | Add zones A, B and bins | Warehouses → Edit → Manage bins |
| 4 | Import categories/brands/units (optional) | Import wizard / manual |
| 5 | Import or create products | Products / product import |
| 6 | Set branch cutover date (future POS block until open) | Branches → Edit |
| 7 | Import opening stock CSV (`warehouse_code`, `sku`, `qty`, `unit_cost`, optional `bin_code`, batch) | Stock levels → Opening stock |
| 8 | Verify quantities | Stock levels |
| 9 | Set reorder points | Branch stock settings |
| 10 | Open store — cutover date passes — POS sells | POS |

### 7.2 Daily replenishment (manual receive)

**Scenario:** Delivery of 48 bottles, batch tracked.

1. Catalog → confirm product exists with **Track batches** enabled.  
2. Inventory → **Receive stock**.  
3. Warehouse: store back room.  
4. Variant: Olive Oil 500ml.  
5. Qty: 48. Batch: `LOT-2406`, Expiry: 2027-01-15.  
6. Bin: `A-01-003` (optional).  
7. Submit → **Stock levels** shows +48 available.

### 7.3 Inter-branch stock move

**Scenario:** Head office warehouse ships 30 units to branch warehouse.

1. Transfers → **New transfer**.  
2. From: HO Warehouse, To: Branch Warehouse.  
3. Add line: 30× target SKU.  
4. Create → open transfer → **Mark shipped**.  
5. Branch user opens same transfer → **Confirm received** → 30.  
6. Both warehouses’ stock levels update.

### 7.4 Damaged stock write-off

1. Adjust stock.  
2. Negative quantity (−5).  
3. Reason: **Damaged**.  
4. Notes for audit trail.

### 7.5 Physical count with variance

1. Cycle counts → New session (full warehouse, freeze on).  
2. Start → teams count → Submit.  
3. System shows variances (+2 / −1 etc.).  
4. Manager approves → Post.  
5. Ledger updated; freeze lifted.

### 7.6 Serialized electronics

1. Product type **Serialized** — “Business Laptop 15””.  
2. Receive 3 units → enter 3 serial numbers: `SN-1001`, `SN-1002`, `SN-1003`.  
3. POS sells one unit → that serial marked sold (via checkout flow).

---

## 8. Permissions reference (for support)

### 8.1 Catalogue permissions

| Permission | Allows |
|------------|--------|
| `products.view` | See products, categories, brands, units |
| `products.create` | Add products |
| `products.update` | Edit products |
| `products.delete` | Delete products |
| `products.show-cost` | View/edit cost price fields |
| `products.import` | Product import wizard |
| `products.export` | Export catalog |

### 8.2 Inventory permissions

| Permission | Allows |
|------------|--------|
| `inventory.view` | Stock levels page |
| `inventory.reports` | Bin stock report, exports |
| `inventory.receive` | Receive stock |
| `inventory.adjust` | Adjust stock, branch stock settings |
| `inventory.transfer` | Stock transfers |
| `inventory.import-opening-stock` | Opening balance import |
| `inventory.bulk-adjustment-import` | Bulk adjustment import |
| `inventory.manage-bins` | Zones/bins CRUD, bin transfer |
| `inventory.release-quarantine` | Quarantine screen |
| `inventory.cycle-count` | Create/run count sessions & schedules |
| `inventory.cycle-count.approve` | Approve count variances |

### 8.3 Warehouse permissions

| Permission | Allows |
|------------|--------|
| `warehouses.view` | Warehouse list |
| `warehouses.create` | Add warehouse |
| `warehouses.update` | Edit warehouse |
| `warehouses.deactivate` | Deactivate warehouse |

### 8.3.1 Dashboard widget permissions

| Permission | Allows |
|------------|--------|
| `dashboard.view` | Open ERP home dashboard |
| `dashboard.inventory.view` | Inventory health widgets on home |
| `dashboard.exceptions.view` | Business exception feed |
| `branches.access-all` | All Branches context (no branch assignment restriction) |

### 8.4 Typical role mapping

| Role | Catalogue | Inventory |
|------|-----------|-----------|
| Super Admin / Owner | Full | Full |
| Branch Manager | Create/edit products | Receive, adjust, approve counts |
| Stock clerk | View | Receive, cycle count entry |
| Cashier (POS) | View (via POS) | No admin inventory (uses POS only) |

Exact role names depend on your tenant seeding. Adjust in **Admin → Roles**.

---

## 9. Import & export

### 9.1 Where to find import/export

- **Products / categories / brands / units:** Export and import from list pages or global import-export gateway (permission required).  
- **Opening stock:** Stock levels → **Opening stock** button.  
- **Bulk adjustments:** Stock levels → **Bulk adjustments** button.  

Jobs run in the **background**. Progress appears in the jobs tray; users can navigate away and return.

### 9.2 Opening stock import columns

| Column | Required | Notes |
|--------|----------|-------|
| `warehouse_code` | Yes | Must match existing warehouse |
| `sku` | Yes | Variant SKU |
| `qty` | Yes | Initial on-hand |
| `batch_no` | If batch-tracked | |
| `expiry_date` | If batch-tracked | |
| `bin_code` | Optional | Resolves to bin location |

**Duplicate rule:** Same warehouse + SKU + batch cannot be imported twice as opening balance.

### 9.3 Bulk adjustment import columns

| Column | Required | Notes |
|--------|----------|-------|
| `warehouse_code` | Yes | |
| `sku` | Yes | |
| `qty_delta` | Yes | Positive or negative |
| `reason` | Yes | `adjustment` or `damaged` only |
| `batch_no` | If batch-tracked | |
| `notes` | Optional | |

### 9.4 Product import (summary)

One spreadsheet row = one product with one variant. Best for **standard** products. Variable matrices and combo bundles should be built in the admin UI.

Key columns: Product Name, SKU, Category Code, Sell Price (required); optional Brand Code, Unit Name, Barcode, Cost Price, Product Type, Active.

Modes: **create**, **update**, **upsert** (match on SKU or barcode).

### 9.5 Import modes

| Mode | Behaviour |
|------|-----------|
| Non-strict (default) | Valid rows import; errors downloadable for bad rows |
| Strict | One bad row aborts entire job |

---

## 10. Troubleshooting & FAQ

### “I can’t find bins / zones”

Go to **Organization → Warehouses → Edit (warehouse) → Manage bins**. Not in the sidebar. Need `inventory.manage-bins`.

### “Manage bins button is missing”

Check: (1) permission `inventory.manage-bins`, (2) warehouse is **active**.

### “Stock levels is empty”

No inventory rows until first **receive** or **opening stock import**. Creating a product alone does not add stock (except service/digital types which don’t track inventory).

### “POS says insufficient stock but stock levels show quantity”

Check **Reserved** column — open POS carts may hold stock. Also check **Quarantine** — not sellable until released. **Available** = on hand − reserved − quarantine.

### “Cannot adjust — batch required”

Product has **Track batches** enabled. Select or enter batch number (and expiry if applicable).

### “Transfer ship fails”

Source **available** quantity too low (consider reservations). Reduce open carts or receive more stock first.

### “Count session — movements blocked”

**Freeze mode** active for that scope. Complete and **post** the count, or cancel session per your process.

### “Branch stock settings says select branch”

User must pick a branch in the **header branch switcher**.

### “Cost price not visible”

User lacks `products.show-cost`. Assign permission or use owner role.

### “Import failed — warehouse_code not found”

Warehouse code in file must exactly match code shown on **Warehouses** list (auto-generated at creation).

### “Opening stock import rejected duplicate”

Opening balance already exists for that warehouse + SKU + batch. Use **adjustment** import or manual adjust instead.

---

## Appendix A — Stock movement reasons (reference)

| Reason | Typical trigger |
|--------|-----------------|
| `opening_balance` | Opening stock import |
| `purchase_receive` | Manual receive |
| `adjustment` | Manual adjustment |
| `damaged` | Write-off adjustment |
| `sale` | POS checkout |
| `transfer_out` / `transfer_in` | Inter-warehouse transfer |
| `reserved` / `reservation_released` | POS cart hold / release |
| `cycle_count_adjustment` | Posted count session |

Movements are **permanent audit records** — they are not edited or deleted.

---

## Appendix B — Related internal docs (for support engineers)

| Document | Content |
|----------|---------|
| `docs/user-manual-put-product-in-stock.md` | Step-by-step: put product in stock for any branch |
| `docs/products-crud.md` | Technical product field reference |
| `docs/phases/phase-04-product-information.md` | Catalogue phase spec |
| `docs/phases/phase-05-inventory-warehouse.md` | Inventory phase spec |
| `docs/opening_stock_import.csv` | Sample opening stock file |
| `docs/bulk_adjustment_import.csv` | Sample adjustment file |

---

## Document history

| Version | Date | Notes |
|---------|------|-------|
| 1.4 | July 2026 | Global Search (Ctrl/Cmd+K) searches pages and business records via permission-aware providers; sidebar uses shared Navigation Registry |
| 1.3 | July 2026 | Home dashboard layout: health strip, sales trends, revenue bar chart, Operations stat-group cards |
| 1.2 | July 2026 | Checkout (confirm + payment) uses the same full-screen POS shell as the register |
| 1.1 | July 2026 | ERP home dashboard is permission-driven business widgets; POS uses a dedicated full-screen shell; login home resolved by permissions |
| 1.0 | June 2026 | Initial customer-facing manual for catalogue & inventory |

*For product updates, verify menu labels against the live admin UI — labels may be refined in future releases.*
