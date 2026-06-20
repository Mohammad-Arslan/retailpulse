# Phase 9 — Customer Relationship & Loyalty Management

**SRS Reference:** §3.9, §3.18 (customers)  
**Status:** Planned  
**Depends on:** Phase 8

---

## Objective

**360° customer profiles**, tiered loyalty, wallets, and store credit integrated with POS/checkout.

## Database (key tables)

- `customers` — name, email, phone, credit_limit, loyalty_tier_id, `tenant_id`
- `loyalty_tiers` — name, multiplier, min_points, auto_upgrade rules
- `loyalty_points` — customer_id, points, type (earn/redeem), sale_id
- `customer_wallets` — balance
- `wallet_transactions` — amount, type, reference

## Features

- Customer CRUD + search in POS
- Profile: transaction history, ATV, preferred payment, tier, wallet, credit balance
- Point rules engine: earn on sale complete; tier multiplier
- Auto tier upgrade/downgrade job
- Wallet top-up, pay-with-wallet at checkout
- Credit limit check on POS (WebSocket warning)
- Permissions: `customers.*`, `customers.view-credit`
- **Bulk import/export (§3.18):** template with name, phone, email, tier, credit_limit, opening wallet balance; `customers.import`, `customers.export`

## Acceptance Criteria

1. Gold tier customer earns 1.5× points on completed sale.
2. Wallet payment reduces balance and records transaction.
3. Credit sale blocked when over limit.
4. Customer import upserts on phone or email without duplicates.

---

## Phase Enhancements (SRS v4.0 — baseline)

### Customer Group Pricing Integration
- Customers can be assigned to a named `customer_group` (e.g., Wholesale, VIP, Staff).
- Customer groups link to price lists defined in §3.21 (Phase 18 — Pricing & Promotions).
- At POS, when a customer is selected, their group's price list is applied automatically to all cart lines.
- Customer group CRUD is added to the Customer admin module; bulk assignment via import.

### Gift Card Lookup at POS
- POS checkout screen includes a "Gift Card" payment method tab (enabled when Phase 24 is active).
- Cashier enters the gift card code; real-time balance check via `/api/v1/gift-cards/{code}/balance`.
- Partial redemption: remaining balance stays on the card; no cash change given.
- Integration point: this phase prepares the customer profile to display owned gift cards; redemption logic lives in Phase 24.

### Store Credit Redemption
- Store credits issued on returns (Phase 14) appear on the customer's 360° profile.
- At POS, "Store Credit" payment method tab deducts from the customer's credit balance.
- Non-transferable; per-customer ledger maintained in `store_credits` table.

### Loyalty Wallet Enhancements (§3.26)
- Wallet top-up flow: admin or cashier can top-up a customer's wallet via cash, card, or bank transfer.
- Wallet expiry policy: configuration key `customers.wallet_expiry_days`; zero means no expiry.
- `customer_wallet_transactions` table stores every debit/credit with reason code and reference.
- Wallet balance and expiry shown prominently in the POS customer search result card.

---

## SRS v4.0 Enhancements (§3.9)

### Accounts Receivable Aging

- **`ar_aging_snapshots`** — nightly ETL: `date`, `customer_id`, `branch_id`, `current`, `bucket_30`, `bucket_60`, `bucket_90`, `bucket_over_90`, `total_outstanding`.
- **AR Aging Report** — outstanding balances by age bucket; filters: branch, customer group, salesperson.
- **Customer Statement** — PDF showing invoices, payments, credit notes, running balance; email from customer profile.
- **Overdue Reminder Workflow** — configurable reminders at day 7/30/60 via SMS, email, WhatsApp; logged per customer.
- **Bad Debt Write-Off** — posts Debit Bad Debt Expense, Credit AR; requires reason code + manager approval; permission `customers.write-off-debt`.
- **Credit Limit Enforcement** — POS blocks credit sale when outstanding AR + new sale > `credit_limit`; manager PIN override.

### Acceptance Criteria (v4.0)

1. AR aging report shows correct buckets for a customer with mixed-age invoices.
2. Customer statement PDF matches ledger balance.
3. Overdue reminder job dispatches at configured day thresholds.
4. Write-off creates balanced journal entry and zeroes customer outstanding balance for written amount.
5. Credit sale blocked at POS when limit exceeded; override with manager PIN succeeds.
