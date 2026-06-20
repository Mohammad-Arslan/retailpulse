# Phase 14 — Notifications, Returns & Tax Engine

**SRS Reference:** §3.15, §3.16, §3.17  
**Status:** Planned  
**Depends on:** Phase 8, Phase 6 (broadcasts)

---

## Objective

**Notification preferences**, **return/refund workflows**, and **composite tax** configuration.

## Database (key tables)

- `notification_preferences` — user_id, event, channels (email, push, database)
- `notifications` (Laravel), `system_broadcasts`
- `returns`, `return_items`, `refund_payments`
- `tax_rates`, `tax_groups`, `tax_group_rates`
- Product/customer: `tax_inclusive` flag

## Features

- Per-user notification prefs; admin broadcast to branch POS terminals
- Return policy: window days, manager PIN if refund > $X
- Multi-mode return: store credit, original payment, exchange
- Tax groups applied as single line, reported separately
- Inclusive/exclusive pricing at product and customer group level
- Permissions: `returns.*`, `returns.approve`, `tax.*`, `notifications.broadcast`

## Acceptance Criteria

1. Return outside 30-day window blocked unless override permission.
2. Composite tax on product shows correct breakdown on invoice.
3. Low-stock email sent only to users who opted in.

---

## Phase Enhancements (SRS v4.0 — baseline)

### Fraud & Operational Controls (§4.4)

**Price Override Logging**
- Every manual line-item discount or price override at POS is recorded in `price_override_logs`: `sale_item_id`, `original_price`, `overridden_price`, `override_reason`, `approved_by` (manager user_id if approval was required), `created_at`.
- Threshold-based approval: discounts exceeding `pos.max_discount_pct` (configurable) require manager PIN before applying.

**Refund Approval Flow Enhancement**
- Refunds above `returns.manager_approval_threshold` (configurable) are blocked until a manager provides their PIN or approves via the Workflow Engine (Phase 29 hook).
- All refund approvals/rejections are audit-logged.

**Void Sale Tracking**
- `void_logs` table: `sale_id`, `voided_by`, `void_reason`, `approved_by` (manager), `voided_at`.
- Void report filterable by cashier, branch, date range — surfaces in the Fraud Controls section of Reporting (Phase 13).

**No-Sale Drawer Log (§3.20)**
- Every manual cash drawer open (without a sale) creates a `no_sale_logs` record: `register_id`, `opened_by`, `reason`, `opened_at`.
- No-sale count by cashier shown on the X/Z report (Phase 17).

**Suspicious Activity Detection**
- A rule-based `SuspiciousActivityService` evaluates the following signals after each transaction:
    - More than N voids in a single shift by the same cashier.
    - Refund amount > sale amount.
    - Discount rate exceeding the configured threshold without manager approval.
    - Multiple payment reversals within a short window.
- Triggered events dispatch a `SuspiciousActivityDetected` notification to the branch manager and log to `audit_logs` with `event = suspicious_activity`.

---

## SRS v4.0 Enhancements (§3.15–3.17)

### Notification Escalation Rules

- If notification not acknowledged within N minutes (configurable), escalate to next role in hierarchy.
- Escalation logged; visible in notification preferences UI.

### Return Reason Codes

- **`return_reason_codes`** — admin-configurable (Defective, Wrong Item, Changed Mind, Size/Fit, etc.).
- Mandatory selection at POS before return completion.
- **Return Reason Report** — volume/value by reason, product, cashier, date range; defect-rate flags.

### Restocking & Condition Grading

- Disposition outcomes after return: **Resalable** (`return_customer` movement to bin), **Damaged** (quarantine + Inventory Write-Down GL), **Scrapped** (Scrapped Goods Expense; no stock movement).
- **Bundle Return Policy** — pro-rata refund for single bundle component; full bundle return option.

### Withholding Tax (WHT)

- **`wht_rates`** — `supplier_category`, `applicable_section` (153, 165), `rate_percent`, `effective_from`.
- Auto-compute WHT on supplier payment; net payment + WHT Payable liability.
- GL on payment: Debit AP (full), Credit Bank (net), Credit WHT Payable.
- WHT remittance workflow: Debit WHT Payable, Credit Bank.
- Section 165 export for FBR filing; permission `tax.export-wht`.

### Tax Return Export

- Sales tax return data: taxable/exempt sales, output/input tax, net payable — FBR XML/CSV export.
- Input/output tax reconciliation report.
- ZATCA/UAE VAT stubs via `FiscalProviderInterface`.

### Acceptance Criteria (v4.0)

1. Return without reason code blocked at POS.
2. Damaged return posts Inventory Write-Down journal; resalable return restores stock at original cost.
3. Supplier payment with WHT posts three-legged journal correctly.
4. Sales tax return export matches sum of fiscal invoices for period.
5. Unacknowledged notification escalates to configured role after timeout.
