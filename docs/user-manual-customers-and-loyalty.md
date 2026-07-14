# RetailPulse User Manual — Customers & Loyalty

**Audience:** Customer support teams, store managers, and cashiers  
**Version:** 1.1 (July 2026)  
**Scope:** Customer profiles, loyalty tiers, wallets, store credit, credit limits, AR aging, and POS/checkout integration

This manual explains **where to click**, **what each screen does**, **how data flows**, and **what every term means**. Hand it to customers who manage CRM, loyalty, and customer payments in RetailPulse.

---

## Table of contents

1. [Before you start](#1-before-you-start)
2. [Glossary — terms and abbreviations](#2-glossary--terms-and-abbreviations)
3. [Admin navigation map](#3-admin-navigation-map)
4. [Configuration (settings)](#4-configuration-settings)
5. [Customer master data](#5-customer-master-data)
6. [Customer groups](#6-customer-groups)
7. [Customer 360° profile](#7-customer-360-profile)
8. [Point of Sale & checkout](#8-point-of-sale--checkout)
9. [Loyalty program](#9-loyalty-program)
10. [Wallet & store credit](#10-wallet--store-credit)
11. [Accounts receivable (AR) & credit limits](#11-accounts-receivable-ar--credit-limits)
12. [End-to-end flow examples](#12-end-to-end-flow-examples)
13. [Permissions reference (for support)](#13-permissions-reference-for-support)
14. [Import & export](#14-import--export)
15. [Troubleshooting & FAQ](#15-troubleshooting--faq)

---

## 1. Before you start

### 1.1 What this manual covers

| Area | What you can do |
|------|-----------------|
| **Customers** | Create, edit, search, import/export, view 360° profile |
| **Customer groups** | Segment customers (Wholesale, VIP, Staff, etc.) |
| **Loyalty** | Tier assignment, points on completed sales, auto tier upgrades |
| **Wallet** | Top-up from admin; pay with wallet at checkout |
| **Store credit** | View balance; redeem at checkout (credits issued on returns — Phase 14) |
| **Credit / AR** | Credit limits, AR aging report, customer statements, overdue reminders |
| **POS / Checkout** | Attach customer, wallet/credit/store-credit payments, manager PIN override |

**Not covered here:** Gift card redemption (Phase 24), customer group price lists (Phase 18), full accounting journal entries (Phase 11). Those integrate with this module but have separate manuals.

**Depends on:** Phase 8 (Checkout, Payments & Invoicing) must be enabled for POS payment flows described here.

### 1.2 Logging in

1. Open your RetailPulse URL (e.g. `https://your-store.example/admin`).
2. Sign in with the email and password provided by your administrator.
3. After login, users with dashboard permissions land on the **ERP Home Dashboard**; cashiers with only POS access open the full-screen register.

### 1.3 Branch context (important)

RetailPulse is **multi-branch**. Many customer metrics (sales history, AR balances, aging snapshots) are filtered by the **active branch** in the header branch switcher.

- Pick the correct branch before reviewing AR aging or branch-scoped sales stats.
- Wallet and loyalty points are **customer-wide** (not per branch).
- AR outstanding on the customer profile can be branch-scoped when a branch is active.

### 1.4 Roles and permissions

Menu items appear only if the user’s role has the required permission. If a customer says “I don’t see AR Aging”, check their role in **Admin → Roles** (see [Section 13](#13-permissions-reference-for-support)).

Sensitive fields (credit limit, AR balance, store credit) require **`customers.view-credit`**.

---

## 2. Glossary — terms and abbreviations

### 2.1 Customer & CRM

| Term | Meaning |
|------|---------|
| **Customer** | A person or business you sell to. Identified by name plus phone and/or email. |
| **360° profile** | Single customer view: contact info, tier, wallet, credit, recent sales, ledgers. |
| **Customer group** | Named segment (e.g. Wholesale, VIP). Used for reporting and future price-list assignment. |
| **ATV** | Average Transaction Value — total spent ÷ number of completed sales (branch-scoped when branch selected). |
| **Preferred payment method** | Stored on the customer record, or inferred from past sale payments if not set. |

### 2.2 Loyalty

| Term | Meaning |
|------|---------|
| **Loyalty tier** | Rank (Bronze, Silver, Gold, Platinum) with a **points multiplier**. |
| **Loyalty points** | Reward currency earned on **completed** sales. |
| **Points multiplier** | Tier factor applied to base points (e.g. Gold = 1.5×). |
| **Auto tier upgrade** | Nightly job (and post-sale recalculation) assigns the highest tier the customer qualifies for by total points. |
| **Earn** | Points added when a sale is fully paid and completed. |
| **Redeem** | Points deducted (future redemption flows; earn is active in Phase 9). |

### 2.3 Wallet & store credit

| Term | Meaning |
|------|---------|
| **Loyalty wallet** | Prepaid balance stored on the customer account (not the same as “mobile wallet” payment gateway). |
| **Wallet top-up** | Adding funds via cash, card, or bank transfer (admin or API). |
| **Wallet expiry** | Optional policy: balance expires N days after last top-up (0 = never). |
| **Store credit** | Non-transferable credit balance (typically from returns). Redeemed at checkout. |
| **Wallet transaction** | Immutable ledger entry: credit (top-up) or debit (checkout, expiry). |

### 2.4 Credit & accounts receivable

| Term | Meaning |
|------|---------|
| **Credit limit** | Maximum outstanding AR the customer may carry. |
| **Credit available** | `Credit limit − AR outstanding` (never below zero). |
| **AR (Accounts Receivable)** | Money the customer owes from credit sales. |
| **Credit sale** | Checkout payment method **Credit** — sale completes but balance remains due. |
| **AR ledger** | Chronological list of invoices, payments, write-offs, and running balance. |
| **AR aging** | Outstanding balances grouped by age: Current, 30, 60, 90, 90+ days. |
| **Aging snapshot** | Nightly saved aging totals per customer per branch. |
| **Customer statement** | PDF summary of invoices, payments, and balance — emailable from profile. |
| **Write-off** | Bad debt removal from AR (requires manager permission; backend ready, admin UI in a later phase). |
| **Manager PIN override** | Allows credit sale when limit would be exceeded (requires valid manager PIN). |

### 2.5 Abbreviations

| Abbr. | Full form |
|-------|-----------|
| **CRM** | Customer Relationship Management |
| **AR** | Accounts Receivable |
| **ATV** | Average Transaction Value |
| **POS** | Point of Sale |
| **RBAC** | Role-Based Access Control |
| **CSV** | Comma-separated values (spreadsheet file) |

---

## 3. Admin navigation map

Sidebar section **Customers**:

```
Customers
  └── Customers           ← list, create, edit, 360° profile
  └── Customer Groups     ← segments (Wholesale, VIP, …)
  └── AR Aging            ← aging report (requires view-credit)
```

Related screens outside this section:

```
Overview
  └── Point of Sale       ← attach customer to cart (F10 area)
  └── Sales               ← completed sale history

Admin
  └── Settings            ← Checkout + Customers & Loyalty groups
```

### 3.1 No separate “Loyalty tiers” menu

Loyalty tiers (Bronze, Silver, Gold, Platinum) are **pre-seeded** at installation. Assign a tier on the customer **Create** or **Edit** form. Automatic upgrades run when **Auto upgrade loyalty tiers** is enabled in settings.

---

## 4. Configuration (settings)

**Path:** Admin → **Settings**

Two setting groups affect this module:

### 4.1 Checkout → payment methods

Enable or disable payment types at POS/checkout:

| Setting | Default | Purpose |
|---------|---------|---------|
| Accept cash | On | Cash tender |
| Accept card | On | Card / gateway |
| Accept mobile wallet | On | Third-party mobile wallet (not customer loyalty wallet) |
| Accept bank transfer | On | Bank transfer with reference fields |
| Accept customer credit | Off | Charge to customer AR account |
| Accept loyalty wallet | Off | Deduct from customer wallet balance |
| Accept store credit | Off | Deduct from customer store credit balance |

**Tip:** Turn on **Accept loyalty wallet**, **Accept store credit**, and **Accept customer credit** when rolling out Phase 9 payment types.

### 4.2 Customers & Loyalty

| Setting | Default | Purpose |
|---------|---------|---------|
| **Wallet expiry (days)** | 0 | Days until wallet balance expires after top-up. **0 = no expiry.** |
| **Loyalty points per 100 spent** | 1 | Base points earned per 100 currency units on completed sales. |
| **Auto upgrade loyalty tiers** | On | Nightly job + post-sale recalculation assigns tier by total points. |
| **AR overdue reminder days** | 7, 30, 60 | Send reminders when balances hit these aging thresholds (email/SMS stubs logged). |

### 4.3 Recommended rollout order

```
1. Configure Customers & Loyalty settings     → Settings → Customers & Loyalty
2. Enable wallet / credit payment methods     → Settings → Checkout
3. Create customer groups (optional)          → Customer Groups
4. Import or create customers                 → Customers
5. Train cashiers on POS customer attach      → Point of Sale
6. Test wallet top-up + checkout payment      → Customer profile + Checkout
7. Set credit limits for account customers    → Customer Edit (view-credit permission)
8. Review AR Aging after first credit sales   → AR Aging (next-day snapshot)
```

---

## 5. Customer master data

### 5.1 Customer list

**Path:** Customers → **Customers**

| Column | Meaning |
|--------|---------|
| Name / contact | Link to 360° profile; phone and email below name |
| Tier | Loyalty tier name |
| Group | Customer group name |
| Credit limit | Shown only with `customers.view-credit` |
| Status | Active or inactive |

**Filters:** Search, loyalty tier, customer group, active/inactive.

**Actions:** View, Edit, Delete (per permissions). **Import / Export** toolbar when permitted.

### 5.2 Creating a customer

**Path:** Customers → **Add customer**

| Field | Required | Notes |
|-------|----------|-------|
| Name | Yes | Display name |
| Phone | No* | Unique if provided |
| Email | No* | Unique if provided |
| NTN | No | Tax identifier (Pakistan NTN) |
| CNIC | No | National ID |
| Credit limit | No | Requires `customers.view-credit` to view/edit |
| Loyalty tier | No | Dropdown — Bronze, Silver, Gold, Platinum |
| Customer group | No | Dropdown of active groups |
| Notes | No | Internal notes |
| Active | Yes (default) | Inactive customers hidden from new POS picks |

\*At least one of phone or email is recommended for deduplication on import.

After save, you are redirected to the **360° profile**.

### 5.3 Editing a customer

**Path:** Customers → row → **Edit**

Same fields as create. Phone and email uniqueness is enforced (ignores the current customer).

### 5.4 Deactivating vs deleting

| Action | Effect |
|--------|--------|
| **Inactive** | Customer stays in history; avoid for new POS attachment |
| **Delete** | Removes customer (only when permitted and safe) |

Prefer **inactive** for customers who no longer shop.

### 5.5 Searching customers (POS & checkout)

**POS:** Type at least **2 characters** of name or phone in the customer search bar above the product search.

**Checkout:** Same search when confirming a sale or adding payment.

Results show name and contact details. Select a row to attach.

---

## 6. Customer groups

**Path:** Customers → **Customer Groups**

Use groups to segment customers for reporting and (in a future release) **price list** assignment.

### 6.1 Creating a group

**Path:** Customer Groups → **Add** (or create from list)

| Field | Purpose |
|-------|---------|
| Name | e.g. “Wholesale”, “VIP”, “Staff” |
| Description | Optional internal note |
| Active | Uncheck to hide from customer forms |

Slug is generated automatically from the name.

### 6.2 Editing a group

**Path:** Customer Groups → row → **Edit**

Update name, description, or active flag. Delete removes the group (customers lose the assignment).

### 6.3 Assigning customers to a group

- **Single customer:** Edit customer → **Customer group** dropdown.  
- **Bulk:** Customer import column `customer_group` (match by name or slug).

---

## 7. Customer 360° profile

**Path:** Customers → click customer name

### 7.1 Header & badges

- Customer name, **Edit**, **Send statement** (email on file + view-credit), **Back to customers**
- Badges: loyalty tier (with multiplier), customer group, inactive flag

### 7.2 Summary cards

| Card | Meaning |
|------|---------|
| **ATV** | Average transaction value (active branch) |
| **Sales count** | Completed sales count (active branch) |
| **Loyalty points** | Lifetime points total |
| **Credit available** | Remaining credit headroom (view-credit only) |

### 7.3 Balances row

| Panel | Meaning |
|-------|---------|
| **Wallet** | Current wallet balance + expiry date if policy enabled. **Top up** button (update permission). |
| **Store credit** | Available store credit balance |
| **AR outstanding** | Amount owed on credit account |

### 7.4 Contact & notes

Phone, email, NTN, CNIC, and free-text notes.

### 7.5 Recent sales

Last 10 completed sales (active branch): invoice number (link to sale), status, total, completed date.

### 7.6 Wallet transactions

Last 20 wallet ledger lines: date, type (credit/debit), reason, amount.

### 7.7 AR ledger

Last 20 AR entries (view-credit): date, entry type, amount, balance after.

### 7.8 Wallet top-up (admin)

1. Open customer profile → **Top up** (wallet panel).  
2. Enter **amount** and **payment method** (cash, card, bank transfer).  
3. Click **Top up**.

Balance updates immediately. A wallet transaction row is created. If wallet expiry is configured, the expiry date refreshes from the top-up date.

### 7.9 Send statement

1. Customer must have an **email** on file.  
2. Click **Send statement**.  
3. System generates a PDF and emails it to the customer.

Requires `customers.view-credit`.

---

## 8. Point of Sale & checkout

### 8.1 Attach customer on POS

**Path:** Overview → **Point of Sale**

1. Select or create a cart tab.  
2. In the **customer row** (below cart tabs), type name or phone (min. 2 characters).  
3. Pick the customer from the dropdown.  
4. Attached customer shows name and phone; click **×** to remove.

The customer is carried into **checkout** when you complete the cart (F10 / checkout flow).

### 8.2 Checkout — confirm sale

**Path:** Opens automatically after POS checkout (**Pay** / F10), or `/admin/checkout/{cartId}`

Checkout uses the same **full-screen POS shell** as the register (no ERP sidebar). The top bar still shows branch, Exit To ERP (if permitted), and user controls.

Before payment:

1. Optionally **search and attach a customer** (same as POS).  
2. Review cart lines and totals. Use **×** on a line to **remove** it (available until a payment is applied). Removing the last line returns you to POS.  
3. Click **Confirm sale** — creates the sale record.

If a customer is attached, the **customer card** shows wallet balance, store credit, AR outstanding, and credit available.

### 8.3 Checkout — take payment

After the sale exists, choose a **payment method**. You can still **remove lines** until the first payment is applied; totals and balance due update automatically.

| Method | Requires customer? | Behaviour |
|--------|-------------------|-----------|
| Cash | No | Optional tendered amount and change |
| Card | No | Gateway stub/live per branch config |
| Mobile wallet | No | External mobile wallet gateway |
| Bank transfer | No | Reference fields per settings |
| **Credit** | **Yes** | Adds to AR; blocked if over credit limit |
| **Wallet** | **Yes** | Deducts wallet balance; shown when balance > 0 |
| **Store credit** | **Yes** | Deducts store credit; shown when balance > 0 |

**Split tender:** If enabled in checkout settings, enter partial payment amounts until balance due reaches zero.

### 8.4 Credit limit block & manager override

When **Credit** would push `AR outstanding + sale total` above **credit limit**:

1. Checkout shows a **credit limit warning**.  
2. Payment is blocked until a **manager PIN** is entered.  
3. Manager must have a valid PIN configured (same POS PIN system).  
4. After override, credit payment proceeds and AR ledger updates.

### 8.5 When is the sale “complete”?

- Balance due = 0 after all payments → sale **completed**, invoice generated, loyalty points earned.  
- Partial payment → sale **partially paid**; take additional payments until complete.  
- Wallet / store credit / credit payments record in both `sale_payments` and the customer ledger.

---

## 9. Loyalty program

### 9.1 Default tiers (seeded)

| Tier | Min points | Multiplier |
|------|------------|------------|
| Bronze | 0 | 1.0× |
| Silver | 500 | 1.25× |
| Gold | 2,000 | 1.5× |
| Platinum | 5,000 | 2.0× |

Tiers are assigned:

- **Manually** on customer create/edit, or  
- **Automatically** when auto-upgrade is on and total points reach `min_points`.

### 9.2 How points are calculated

On each **completed** sale with a customer attached:

```
base_points = floor(grand_total ÷ 100) × loyalty_points_per_100
earned      = floor(base_points × tier_multiplier)
```

**Example:** Grand total 580, setting = 1 point per 100, Silver tier (1.25×):

- Base = floor(580 / 100) × 1 = **5**  
- Earned = floor(5 × 1.25) = **6 points**

Points appear on the customer profile **Loyalty points** card and in the `loyalty_points` ledger.

### 9.3 Automatic tier jobs

| Job | Schedule | Purpose |
|-----|----------|---------|
| Recalculate loyalty tiers | Daily 03:00 | Re-evaluates tier for all customers |
| (Also) | After each completed sale | Immediate tier recalculation |

Disable **Auto upgrade loyalty tiers** in settings to keep tiers manual-only.

---

## 10. Wallet & store credit

### 10.1 Loyalty wallet lifecycle

```
Top-up (admin/import/checkout API)
    → Wallet balance increases
    → Transaction: credit / reason: top_up
    → Expiry refreshed (if policy enabled)

Checkout payment (wallet method)
    → Balance decreases
    → Transaction: debit / reason: checkout

Expiry job (if wallet_expiry_days > 0)
    → Expired balance zeroed
    → Transaction: debit / reason: expiry
```

### 10.2 Top-up channels

| Channel | Who | Payment methods |
|---------|-----|-----------------|
| Admin profile | Staff with `customers.update` | Cash, card, bank transfer |
| Import | `opening_wallet_balance` column | N/A (opening balance) |
| API | POS integrations | Per API request |

### 10.3 Pay with wallet at checkout

1. Enable **Accept loyalty wallet** in Settings → Checkout.  
2. Attach customer with wallet balance > 0.  
3. **Wallet** appears as a payment method on checkout.  
4. Select wallet → pay (full or split amount).  
5. Balance and transaction history update on the profile.

### 10.4 Store credit

- **Balance** shown on customer profile (view-credit).  
- **Issuance:** Return/refund workflows (Phase 14) credit the `store_credits` ledger.  
- **Redemption:** Enable **Accept store credit** in checkout settings; method appears when customer has balance > 0.  
- **Non-transferable** — tied to the customer record only.

---

## 11. Accounts receivable (AR) & credit limits

### 11.1 Setting a credit limit

**Path:** Customers → Edit → **Credit limit**

Only visible with `customers.view-credit`. Leave blank for no limit (not recommended for account customers).

### 11.2 Credit sales at checkout

1. Enable **Accept customer credit** in Settings → Checkout.  
2. Attach customer at checkout.  
3. Select **Credit** payment method.  
4. System records AR ledger **invoice** entry; outstanding balance increases.

### 11.3 AR ledger (customer profile)

Entry types include:

| Type | Meaning |
|------|---------|
| Invoice | Credit sale — increases balance |
| Payment | Customer payment — decreases balance |
| Credit note | Adjustment credit |
| Write-off | Bad debt removal (manager permission) |

### 11.4 AR Aging report

**Path:** Customers → **AR Aging**

Requires `customers.view-credit` and an active branch (defaults to header branch).

| Column | Bucket |
|--------|--------|
| Current | Not yet 30 days overdue |
| 30 days | 30–59 days |
| 60 days | 60–89 days |
| 90 days | 90–119 days |
| 90+ | 120+ days |
| Total outstanding | Sum of buckets |

**Filters:** Search (customer name/phone), customer group.

Snapshots are built **nightly at 02:30**. Same-day credit activity may not appear until the next snapshot.

**Export:** Use export action when available (permission: `customers.export`).

### 11.5 Overdue reminders

Configured **AR overdue reminder days** (e.g. 7, 30, 60) trigger a daily job at **08:00**. Reminders are logged per customer; email/SMS/WhatsApp delivery depends on your notification setup.

### 11.6 Customer statement

PDF generated from AR ledger + invoice history. Sent via **Send statement** on the customer profile (requires email + view-credit permission).

---

## 12. End-to-end flow examples

### 12.1 New VIP customer with wallet opening balance

| Step | Action | Screen |
|------|--------|--------|
| 1 | Create group “VIP” | Customer Groups |
| 2 | Create customer, assign VIP + Gold tier | Customers → Add |
| 3 | Top up wallet 5,000 via bank transfer | Customer profile → Top up |
| 4 | Cashier attaches customer on POS | Point of Sale |
| 5 | Complete cart → checkout → pay with **Wallet** | Checkout |
| 6 | Verify wallet balance decreased | Customer profile |

### 12.2 Loyalty earn on a cash sale

| Step | Action |
|------|--------|
| 1 | Attach Silver-tier customer on POS |
| 2 | Ring sale 250.00, checkout, pay **Cash** in full |
| 3 | Sale completes → points earned (floor(250/100)×1 × 1.25) |
| 4 | Open profile → confirm loyalty points increased |

### 12.3 Credit account customer near limit

| Step | Action |
|------|--------|
| 1 | Set credit limit 10,000; customer owes 9,500 AR |
| 2 | New sale 1,000 → checkout → **Credit** |
| 3 | System warns: exceeds limit by 500 |
| 4 | Manager enters PIN → sale completes |
| 5 | AR ledger shows new invoice; aging updates next night |

### 12.4 Bulk customer import at go-live

| Step | Action |
|------|--------|
| 1 | Prepare CSV: name, phone, email, tier, customer_group, credit_limit, opening_wallet_balance |
| 2 | Customers → Import → upload file |
| 3 | Match mode upserts on phone or email |
| 4 | Review job tray for errors |
| 5 | Spot-check profiles and wallet balances |

### 12.5 Monthly AR review

| Step | Action |
|------|--------|
| 1 | Select branch in header |
| 2 | Open **AR Aging** |
| 3 | Filter by group “Wholesale” |
| 4 | Open high **90+** customers → profile → AR ledger |
| 5 | Send statement or follow up per credit policy |

---

## 13. Permissions reference (for support)

### 13.1 Customer permissions

| Permission | Allows |
|------------|--------|
| `customers.view` | Customer list, groups, profile (without credit fields), POS search |
| `customers.create` | Add customers and groups |
| `customers.update` | Edit customers, groups, **wallet top-up** |
| `customers.delete` | Delete customers and groups |
| `customers.view-credit` | Credit limit, AR balance, store credit, AR ledger, AR Aging, statements |
| `customers.import` | Customer import wizard |
| `customers.export` | Customer / AR export |
| `customers.write-off-debt` | AR write-off (API/backend; admin UI pending) |

### 13.2 Related checkout permissions

| Permission | Allows |
|------------|--------|
| `pos.access` | POS and checkout screens |
| `sales.view` | View sale detail from customer recent sales |

### 13.3 Settings permissions

| Permission | Allows |
|------------|--------|
| `settings.view` | View settings pages |
| `settings.general.update` | Save **Customers & Loyalty** and **Checkout** groups |

### 13.4 Typical role mapping

| Role | Customers | Credit / AR | Wallet top-up |
|------|-----------|-------------|---------------|
| Super Admin / Owner | Full | Full | Yes |
| Branch Manager | Create/edit | View + statements | Yes |
| Cashier | View, POS attach | No | No |
| Credit controller | View | Full + aging | No |

Exact role names depend on your tenant seeding. Adjust in **Admin → Roles**.

---

## 14. Import & export

### 14.1 Where to find import/export

**Path:** Customers → **Customers** list → Import/Export toolbar

Requires `customers.import` / `customers.export`. Jobs run in the **background**; progress appears in the jobs tray.

### 14.2 Customer import columns

| Column | Required | Notes |
|--------|----------|-------|
| `name` | Yes | Customer display name |
| `phone` | No* | Upsert match key |
| `email` | No* | Upsert match key |
| `tier` | No | Loyalty tier **slug** or name (e.g. `gold`, `Gold`) |
| `customer_group` | No | Group slug or name |
| `credit_limit` | No | Numeric; blank = no limit |
| `opening_wallet_balance` | No | Creates wallet top-up on import |
| `is_active` | No | true/false; default active |

\*At least one of **phone** or **email** per row.

**Upsert rule:** Existing customers matched by phone or email are updated; otherwise a new record is created. Duplicates on phone/email within the file fail validation.

### 14.3 Customer export

Exports the same column set as import, plus current wallet balance and tier/group resolved to names. Respects list filters (search, tier, group, status) when exported from the filtered list.

### 14.4 Import modes

| Mode | Behaviour |
|------|-----------|
| Non-strict (default) | Valid rows import; errors downloadable for bad rows |
| Strict | One bad row aborts entire job |

---

## 15. Troubleshooting & FAQ

### “Customer profile crashes or blank after wallet payment”

Hard-refresh the browser. If it persists, support should verify the customer show page receives `customer`, `stats`, and `walletTransactions` props (known integration issue fixed in Phase 9).

### “Wallet top-up failed”

- User needs **`customers.update`**.  
- Amount must be > 0.  
- Check Laravel log for validation errors.

### “Wallet payment not shown at checkout”

1. **Settings → Checkout → Accept loyalty wallet** must be **on**.  
2. Customer must be **attached**.  
3. Wallet **balance must be > 0** (method hidden when zero).

### “Credit sale blocked — credit limit exceeded”

Expected behaviour. Manager must enter a valid **manager PIN** to override, or reduce the sale / collect partial payment with other methods.

### “Credit limit column shows — in customer list”

User lacks **`customers.view-credit`**, or customer has no credit limit set.

### “AR Aging is empty”

- Snapshots run **nightly** — no credit sales yet, or branch filter excludes data.  
- Ensure **branch** is selected in header.  
- Customer needs credit sales creating AR ledger entries.

### “Loyalty points not awarded”

- Sale must be **fully paid and completed** (not partially paid).  
- Customer must be attached before confirm.  
- Check **Loyalty points per 100 spent** > 0 in settings.  
- Very small totals may round to zero base points.

### “Tier did not upgrade after big purchase”

- Confirm **Auto upgrade loyalty tiers** is enabled.  
- Tier also recalculates nightly — wait until after 03:00 job, or check total points vs tier `min_points`.  
- Manually assigned tier is overwritten on auto-upgrade when points qualify for higher tier.

### “Store credit payment not available”

Enable **Accept store credit** in checkout settings; customer must have store credit balance > 0. Credits are issued from **returns** (Phase 14) — none yet if no returns processed.

### “Send statement does nothing”

Customer needs an **email** on file. User needs **`customers.view-credit`**. Check mail configuration on the server.

### “Import failed — phone or email required”

Each row needs at least one contact field for upsert matching.

### “Import tier/group not applied”

Tier and group values must match an existing **slug or name** exactly (case-sensitive for slug). Create tiers/groups first or fix spreadsheet values.

### “POS customer search returns nothing”

- Type at least **2 characters**.  
- Customer must be **active**.  
- User needs **`customers.view`**.

---

## Appendix A — Scheduled background jobs

| Job | Time (server) | Purpose |
|-----|----------------|---------|
| Build AR aging snapshots | 02:30 daily | Populate AR Aging report |
| Recalculate loyalty tiers | 03:00 daily | Auto tier assignment |
| Send overdue reminders | 08:00 daily | AR reminder workflow |

Ensure Laravel scheduler is running in production (`schedule:run` every minute).

---

## Appendix B — Payment method quick reference

| Checkout label | DB / enum value | Customer required |
|----------------|-----------------|-------------------|
| Cash | `cash` | No |
| Card | `card` | No |
| Mobile wallet | `mobile_wallet` | No |
| Bank transfer | `bank_transfer` | No |
| Customer credit | `credit` | Yes |
| Loyalty wallet | `wallet` | Yes |
| Store credit | `store_credit` | Yes |

---

## Appendix C — Related internal docs (for support engineers)

| Document | Content |
|----------|---------|
| `docs/phases/phase-09-customers-loyalty.md` | Phase 9 technical spec |
| `docs/phases/phase-08-checkout-payments-invoicing.md` | Checkout & payment pipeline |
| `docs/phases/phase-07-point-of-sale.md` | POS cart behaviour |
| `docs/user-manual-inventory-and-catalogue.md` | Catalogue & inventory manual (sibling doc) |

---

## Document history

| Version | Date | Notes |
|---------|------|-------|
| 1.3 | July 2026 | Checkout allows removing cart lines until a payment is applied |
| 1.2 | July 2026 | Checkout uses the same full-screen POS shell as the register (no ERP sidebar) |
| 1.1 | July 2026 | Login home resolved by permissions (dashboard vs full-screen POS) |
| 1.0 | June 2026 | Initial customer-facing manual for Phase 9 — customers, loyalty, wallet, credit, AR |

*For product updates, verify menu labels against the live admin UI — labels may be refined in future releases.*
