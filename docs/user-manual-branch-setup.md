# RetailPulse User Manual — Branch Setup & Onboarding

**Audience:** Store admins and implementation/support teams standing up a **new branch**  
**Version:** 1.0 (July 2026)  
**Scope:** The order of operations to take a brand-new branch from "just created" to "selling on POS with accounting posting correctly"

This guide assumes the branch itself does not exist yet. For day-to-day stock operations at an already-configured branch, see [`user-manual-put-product-in-stock.md`](user-manual-put-product-in-stock.md). For chart of accounts, journals, and posting detail, see [`user-manual-accounting-and-finance.md`](user-manual-accounting-and-finance.md).

---

## Table of contents

1. [How modules & branch scope work (read first)](#1-how-modules--branch-scope-work-read-first)
2. [Create the branch](#2-create-the-branch)
3. [Warehouses → Zones → Bins](#3-warehouses--zones--bins)
4. [Enable modules](#4-enable-modules)
5. [Accounting basics](#5-accounting-basics)
6. [People](#6-people)
7. [Products & stock](#7-products--stock)
8. [Go live](#8-go-live)
9. [Known limitations (as of this release)](#9-known-limitations-as-of-this-release)

---

## 1. How modules & branch scope work (read first)

RetailPulse is one system, not a set of synced apps — but whole **feature areas are switched on per branch**, and most screens only ever show data for branches a user is **assigned** to. Understanding this before you start setup avoids the most common "why is this menu missing" confusion.

| Concept | What it means |
|---|---|
| **Module gate** | A whole feature area (HR, Payroll, Attendance, Leave, Accounting sub-modules) is enabled or disabled **per branch**. If a module is off for a branch, its screens/menus won't appear for that branch's context, even for an otherwise fully-permissioned user. |
| **Branch assignment** | A user only sees data for the branch(es) they are explicitly assigned to on **Users**. This applies across HR, Payroll, most Accounting lists, and Inventory. |
| **`hr-module:*` gate** | HR routes (Employees, Departments, Designations, Grades, Attendance, Leave, Payroll) are gated by an HR-module-enabled check per branch — e.g. `hr-module:hr`, `hr-module:payroll`. |
| **`accounting-module:*` gate** | Accounting routes (Currencies, Tax Types, Credit/Debit Notes, etc.) are gated the same way per branch — e.g. `accounting-module:multi_currency`, `accounting-module:tax`. |

> **Important — known footgun:** a user who has **no branch assigned** is currently treated as **company-wide** (they can see across all branches), not "no access." Always explicitly assign every non-head-office user to their correct branch on the **Users** screen — do not rely on "no assignment" to mean "restricted."

With that in mind, set up a new branch roughly in this order: **branch → warehouses/zones/bins → modules → accounting basics → people → products/stock → go live**. Skipping ahead (e.g. enabling payroll before HR, or trying to sell before a fiscal year exists) is the most common source of setup errors below.

---

## 2. Create the branch

### Steps

1. Go to **Organization → Branches**.
2. Click **Create**.
3. Fill in the fields:

| Field | Notes |
|---|---|
| **Name** | Display name shown throughout the app. |
| **Code** | Unique, up to 32 characters. Use the **Suggest Code** helper if you don't have a convention yet. |
| **Address** | Free text. |
| **Currency** | 3-letter code (e.g. `PKR`, `USD`). This is a **branch-level field** — a single-currency branch does not need to touch the **Currencies** screen at all. |
| **Timezone** | Used for reports, shift/day boundaries, and scheduled jobs scoped to this branch. |
| **Operating hours** | Store open/close time. |
| **Weekend days** | Which days this branch is normally closed. |
| **Receipt footer** | Printed on POS receipts for this branch. |
| **Active** | Must be checked for the branch to be usable anywhere else in the app (POS, HR, Accounting). |

4. Save.

> **Note:** creating the branch does **not** provision anything else automatically — no warehouse, no chart of accounts, no fiscal year, no modules enabled. Every stage below is a separate, deliberate step.

---

## 3. Warehouses → Zones → Bins

Stock is tracked **per warehouse**, and every warehouse belongs to exactly one branch. Nothing here is created for you when the branch is created.

### Steps

1. Go to **Organization → Warehouses** and create at least one warehouse for the new branch.
2. Choose a **type**: `sales_floor`, `backroom`, `offsite`, or `central`.
3. Mark **one warehouse as default** for the branch — POS and most stock operations default to it unless a different warehouse is explicitly picked.
4. Inside the warehouse, add **zones** — logical areas (e.g. "Front of store", "Cold storage"). **Add zones before bins**; a bin must belong to a zone.
5. Add **bins** within each zone if you use bin-level tracking. **Bin code must be unique per warehouse** (not globally).

> **Tip:** if you don't need bin-level precision yet, you can skip zones/bins and still receive/sell stock at the warehouse level — bins are optional granularity, not a hard requirement to go live.

---

## 4. Enable modules

Module toggles are per branch. Two separate screens control HR/Payroll modules and Accounting sub-modules.

### HR modules

| Module | Depends on | Notes |
|---|---|---|
| `hr` | — | Enable this **first** — Employees, Departments, Designations, Grades all sit behind it. |
| `attendance` | `hr` | |
| `leave` | `hr` | |
| `payroll` | `hr` | |
| `overtime` | `attendance` | Cannot be enabled until `attendance` is on for the branch. |

### Accounting modules

| Module | Depends on | Notes |
|---|---|---|
| `core` | — | Always on; not a toggle. |
| `ar_ap` | — | Enable for a normal trading branch (accounts receivable/payable). |
| `tax` | — | Enable to use Tax Types for this branch. |
| `credit_notes` / `debit_notes` | `ar_ap` | Cannot be enabled without `ar_ap`. |
| `multi_currency` | — | Only needed if this branch transacts in more than one currency; a single-currency branch should leave this off (see Section 2 — currency is already a branch field). |

For a typical single-currency retail branch: enable `hr`, `attendance`, `leave`, `payroll` (HR side) and `ar_ap`, `tax` (Accounting side). Leave `multi_currency` off unless genuinely needed.

---

## 5. Accounting basics

Do these in order — accounting will not post correctly if you skip ahead, and (per the rule below) **it will not post at all** without a fiscal year.

### Step 1 — Chart of Accounts

Go to **Accounting → Chart of Accounts**. There is **no built-in template** — either:
- Hand-enter accounts, or
- Use the **COA import**, then **approve** the imported batch.

### Step 2 — Create an Open Fiscal Year covering today

Go to **Accounting → Fiscal Years** and create a fiscal year with status **Open** whose date range covers today's date, **before this branch processes any sale, purchase, or payroll**.

> **Important:** posting a journal — manual, or automatic from a sale/purchase/payroll — **requires an open fiscal year covering the entry date**. If none exists, the action is blocked with a clear message rather than silently succeeding. Create the fiscal year first; there is no way to post around this.

### Step 3 — Tax Types

Go to **Accounting → Tax Types** (requires the `tax` module from Section 4) and set up the tax rates this branch charges.

### Step 4 — Account Mappings & Posting Rules

Go to **Accounting → Account Mappings** and **Posting Rules** to confirm sales, COGS, tax, and payroll postings map to the correct Chart of Accounts entries. Most of this is seeded/shared across branches — only touch it if this branch needs a different mapping.

---

## 6. People

### Step 1 — Roles

Go to **Roles**. Clone an existing role close to what you need rather than starting from scratch.

### Step 2 — Users

Go to **Users**, create the accounts this branch needs, and **assign each user to this branch** (see the Section 1 footgun — do not leave this blank).

### Step 3 — HR structure

Set up **Departments**, **Designations**, and **Grades** under HR. A **Holiday Calendar** is optional but recommended if this branch has different holidays than others.

### Step 4 — Employees

Create employees under **HR → Employees**. If an employee should be able to log in, link them to their user account via the **Linked User** field on the employee form — no database editing needed.

---

## 7. Products & stock

### Step 1 — Catalogue

Products are shared across the company, not per-branch. Create products under **Catalog → Products**, or bulk-import them.

### Step 2 — Per-branch stock settings

Go to **Inventory → Branch Stock Settings** to set branch-specific **reorder point** and **safety stock** overrides on top of each product's catalogue-level default reorder point.

### Step 3 — Opening stock

Get physical stock "in hand" for this branch using whichever fits:
- **Opening stock import** into this branch's warehouse(s) for a bulk go-live, or
- **Receive stock** / **Adjust stock** for smaller quantities.

See [`user-manual-put-product-in-stock.md`](user-manual-put-product-in-stock.md) for the full walkthrough of each method.

---

## 8. Go live

### Step 1 — POS payment methods

Confirm which payment methods this branch accepts. The supported set is: `cash`, `card`, `mobile_wallet`, `bank_transfer`, `credit`, `wallet`, `store_credit`.

### Step 2 — Test sale

Run one test sale through POS at this branch. Confirm:
- Stock is deducted at the correct warehouse.
- A journal entry is created and **posted** (this requires the Step 5.2 fiscal year to exist — if it doesn't, the sale will be blocked with a clear error before anything is mutated, rather than completing with broken accounting).

### Step 3 — Test purchase

Receive one test purchase order / GRN. Confirm stock increases and the corresponding journal posts.

### Step 4 — Confirm accounting

Open **Accounting → Fiscal Years** and confirm a fiscal year is shown as covering today, then spot-check the journals created by your test sale and purchase.

---

## 9. Known limitations (as of this release)

- **POS has no register/shift binding yet.** Checkout does not require opening or closing a till session.
- **Online payment gateways are not live.** Card/mobile-wallet payment methods are recorded, but there is no live gateway integration — treat non-cash payments as manually confirmed for now.

---

## Related documents

| Document | Content |
|---|---|
| [`user-manual-put-product-in-stock.md`](user-manual-put-product-in-stock.md) | Detailed stock-in methods (receive/adjust/transfer/import/PO) |
| [`user-manual-accounting-and-finance.md`](user-manual-accounting-and-finance.md) | Full chart of accounts, journals, fiscal years, tax |
| [`user-manual-inventory-and-catalogue.md`](user-manual-inventory-and-catalogue.md) | Full catalogue & inventory manual |

---

## Document history

| Version | Date | Notes |
|---|---|---|
| 1.0 | July 2026 | Initial guide — branch setup and onboarding order of operations |
