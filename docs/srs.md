# Enterprise Point of Sale & Retail Management System — Requirements Specification

**Document Version:** 4.0  
**Date:** 2026-06-19  
**Status:** Living Document — Actively Extended  
**Revision:** v3.0 → v4.0 Gap Analysis & Full Rewrite

## What's New in v4.0

All gaps identified in the v3.0 review have been addressed. Key additions:

- Purchase Return / RMA, Landed Cost, 3-Way PO Matching
- Cycle Count, Bin-Level Inventory, Quarantine Stock
- AR Aging, Multi-Currency, Inter-Company Accounting
- COGS Posting Specification
- Leave Management, Overtime Engine
- WHT Compliance, Tax Return Exports
- Workflow Engine (full spec at §3.30)
- Document Vault (§3.31)
- Disaster Recovery (RTO/RPO), Testing Strategy

Sections marked **New in v4.0** throughout were added in this revision.

> **This SRS defines *what* RetailPulse must do.** For *how* the system is architected to deliver it durably — multi-tenancy strategy, module boundaries, layering, audit trail, workflow engine, integration strategy, API strategy, plugin architecture, security principles, coding standards, and frontend architecture — see the authoritative [Architecture Decision Records](./architecture/README.md). Where an implementation detail here and an ADR appear to disagree, the ADR governs unless it has been formally updated.

---

## Table of Contents

1. **Introduction & Project Vision**
2. **System Architecture & Technology Stack**
3. **Core Functional Requirements**
   - 3.1 Authentication & Identity Management
   - 3.2 Authorization & Role-Based Access Control
   - 3.3 Dashboard & Real-Time Business Intelligence
   - 3.4 Multi-Branch & Centralized Management
   - 3.5 Product Information Management (PIM)
   - 3.6 Inventory & Warehouse Management
   - 3.7 Point of Sale (POS) Interface
   - 3.8 Checkout, Payments & Invoicing
   - 3.9 Customer Relationship & Loyalty Management
   - 3.10 Supplier & Purchase Order Management
   - 3.11 Accounting & Financial Management
   - 3.12 Expense Management
   - 3.13 Human Resources & Payroll
   - 3.14 Advanced Reporting & Analytics
   - 3.15 Notification Engine
   - 3.16 Refund, Return & Exchange Management
   - 3.17 Tax Configuration Engine
   - 3.18 Data Import, Export & Customer Onboarding
   - 3.19 Restaurant Management Module
   - 3.20 Shift & Cash Register Management
   - 3.21 Advanced Pricing & Promotions Engine
   - 3.22 Hardware Integration Layer
   - 3.23 Recipe & Ingredient Management
   - 3.24 Modular Feature Management & Subscription Architecture
   - 3.25 Global Configuration Engine
   - 3.26 Gift Cards, Store Credits & Loyalty Wallet Enhancements
   - 3.27 E-Commerce & Omni-Channel Integration
   - 3.28 Mobile Applications
   - 3.29 Business Intelligence & Data Warehouse Readiness
   - 3.30 Workflow Engine
   - 3.31 Document Vault & Attachment Management
4. **Non-Functional & Operational Requirements**
5. **Data Architecture & Database Design**
6. **Third-Party & External Integrations**
7. **Deployment, DevOps & Infrastructure**

---

### 1. Introduction & Project Vision

The goal is to build a **next-generation, enterprise-grade Point of Sale (POS) and Retail Management Ecosystem** designed for high-volume, multi-branch operations. The system will move beyond transactional processing to provide real-time operational command, AI-assisted insights, and an architecture designed for global SaaS expansion. It will serve as the central nervous system for retail businesses, unifying sales, inventory, finance, human resources, and supply chain into a single, high-performance platform.

### 2. System Architecture & Technology Stack

The system will follow a **modular, domain-driven, API-first monolith** architecture, structured for a future migration to microservices. The codebase will enforce strict separation of concerns.

| Layer | Technology | Justification |
| :--- | :--- | :--- |
| **Backend Framework** | Laravel 13 | High-performance core with native queue, event, and real-time systems. |
| **Frontend** | React 19 with Inertia.js 2.0 | Seamless SPA-like UX without a separate API build step for the core app. |
| **UI Components & Styling** | shadcn/ui & Tailwind CSS 4 | Accessible, composable component primitives with a utility-first styling approach. |
| **Database** | MySQL 8.4 | Robust relational model for transactional integrity and complex reporting. |
| **Real-Time Engine** | Laravel Reverb over WebSockets | Scalable, server-pushed updates for dashboards, POS terminals, and stock levels. |
| **Auth & Permissions** | Laravel Breeze + Sanctum + Spatie Permission | Breeze scaffolding; Sanctum for API tokens; Spatie for granular RBAC. |
| **Cache & Queues** | Redis (via Laravel) | Session, cache, and queue drivers fully externalized for horizontal scale. |

**Backend Architecture Pattern:**

- **Services:** Encapsulate all business logic.
- **Repositories:** Abstract all database interactions.
- **Data Transfer Objects (DTOs):** Strictly typed objects for data passing between layers.
- **Events & Jobs:** Decoupled, queue-driven system for post-transaction processes (e.g., sending invoice, updating loyalty points, triggering reorder alerts).
- **API Versioning:** All API routes will be prefixed with `/api/v1/` to ensure non-breaking future changes.

### 3. Core Functional Requirements

#### 3.1. Authentication & Identity Management

- **Multi-Mode Login:** Support for standard email/password, biometric-ready PIN for cashiers, and magic link options.
- **Advanced Session & Device Management:** Users can view and remotely terminate active sessions. Device fingerprinting is logged for security auditing.
- **Two-Factor Authentication (2FA):** Mandatory for high-privilege roles (Super Admin, Owner, Accountant). Support for TOTP authenticator apps and backup codes.
- **Secure API Authentication:** Use Laravel Sanctum for both the SPA frontend and stateless API tokens for third-party integrations, with the ability to define granular token abilities.

#### 3.2. Authorization & Role-Based Access Control

- **Predefined Dynamic Roles:** A starter set of roles (Super Admin, Branch Manager, Cashier, Accountant, Procurement Officer, Warehouse Staff) that are fully editable.
- **Granular Permissions Manager:** An administrative UI for creating, grouping, and assigning permissions. Permissions can control UI element visibility (e.g., a "Show Cost Price" permission) and API access.
- **Role Cloning:** Create a new role by duplicating an existing one's permissions.
- **Permission Inheritance:** A Branch Manager can inherit all permissions of a Cashier plus their own.
- **User-Specific Overrides:** Grant temporary or permanent extra permissions to a single user without changing their core role.

#### 3.3. Dashboard & Real-Time Business Intelligence

- **KPI Widgets:** Configurable dashboard cards for Today's Sales, Gross Profit, Average Transaction Value, Low Stock Alerts, Pending Approvals, Overdue Receivables, and Pending PO Receipts.
- **Real-Time Event Stream:** A live activity feed updated via Laravel Reverb showing high-value events.
- **Comparative Charts:** Interactive charts (Recharts) for Week-over-Week and Month-over-Month revenue, profit, and customer footfall, filterable by branch.

#### 3.4. Multi-Branch & Centralized Management

- **Head Office Console:** A central view for entity-level reporting and configuration management across all branches.
- **Branch Isolation with Sync:** Each branch has its own inventory quantities, pricing overrides, and tax configurations, but product master data can be managed centrally and synced down.
- **Operational Settings:** Define per-branch settings for currency, timezone, operating hours, default warehouse, and receipt footer.
- **Warehouse Management:** Each branch may have **one or more warehouses**. A dedicated admin CRUD (permission-gated: `warehouses.view`, `warehouses.create`, `warehouses.update`, `warehouses.deactivate`) manages warehouse name, code, default flag, and soft deactivation. Branch create seeds the first default warehouse; additional warehouses are added via warehouse admin. Inactive warehouses are excluded from stock operations.

#### 3.5. Product Information Management (PIM)

- **Rich Product Types:** Support for Standard, Variable (Size/Color), Service, Digital Download, Serialized (IMEI/VIN), and Combo/Bundle products.
- **Automated Identifiers:** System generates unique SKUs and Barcodes (EAN-13, UPC-A, CODE128) based on configurable patterns.
- **Advanced Inventory Tracking:** Track by Batch/Lot Number and Expiry Date (FEFO) or FIFO. Full audit trail for product master changes.
- **Preferred Supplier per Product:** Each product variant can have a designated primary supplier and optional alternate suppliers, used by the auto-reorder engine.

#### 3.6. Inventory & Warehouse Management

- **Multi-Warehouse Stock Ledger:** Real-time stock ledger with columns for `warehouse_id`, `product_variant_id`, `bin_location_id`, `batch_no`, `expiry_date`, `quantity_on_hand`, `quantity_reserved`, and `quantity_in_quarantine`.
- **Inventory Transactions:** Immutable stock movement log with reasons: `sale`, `purchase_receive`, `transfer_out`, `transfer_in`, `adjustment`, `damaged`, `return_customer`, `return_supplier`, `production_consume`, `production_output`, `opening_balance`, `cycle_count_adjustment`.
- **Negative Stock Prevention:** Configurable per-branch policy to block or warn on negative stock sales.
- **Smart Transfers:** Stock transfers between branches/warehouses with shipped and received confirmation status. Corresponding intercompany journal entries are auto-posted on confirmation (see §3.11).

**Bin & Location Management** *(New in v4.0)*

- **Warehouse Layout:** Each warehouse is subdivided into zones, aisles, shelves, and bins. Hierarchy: Warehouse → Zone → Aisle → Shelf → Bin.
- **`bin_locations` table:** `warehouse_id`, `zone`, `aisle`, `shelf`, `bin_code`, `is_active`, `capacity_limit` (optional).
- Stock is tracked at the bin level: `inventories` table includes `bin_location_id` as a foreign key.
- POS and GRN screens display bin suggestions based on FEFO/FIFO rules. Warehouse staff confirm or override the suggested bin.
- **Bin Transfer:** A lightweight internal transfer moves stock between bins in the same warehouse without a full inter-branch transfer workflow.
- **Bin Report:** Shows current stock per bin, useful for stocktaking and pick-path optimization.

**Physical Stock Count & Cycle Count** *(New in v4.0)*

- **Count Session Workflow:** Create Count Session → Assign Scope (full warehouse, zone, or category) → Generate Count Sheets → Count Entry → Variance Review → Manager Approval → Post Adjustments.
- **Freeze Mode:** When a count session is active for a bin or zone, stock movements in that scope are queued and applied after the count is posted to prevent phantom variances.
- **Blind Count Option:** Warehouse staff enter physical counts without seeing system quantities. System calculates variance only after submission.
- **Variance Threshold:** Variances above a configurable value or percentage require Branch Manager approval before posting.
- **`count_sessions` table:** `branch_id`, `warehouse_id`, `scope_type` (full/zone/category), `scope_id`, `status` (draft/in_progress/under_review/approved/posted), `created_by`, `approved_by`, `posted_at`.
- **`count_session_lines` table:** `session_id`, `product_variant_id`, `bin_location_id`, `batch_no`, `system_qty`, `counted_qty`, `variance_qty`, `variance_value`, `adjustment_reason`.
- Posted count adjustments create immutable `stock_movements` records with `reason = cycle_count_adjustment` and link to the session for full audit trail.
- **Cycle Count Scheduling:** Admin can schedule recurring counts (e.g., high-value SKUs weekly, slow-movers quarterly) with automated session creation.
- **Mobile Support:** Warehouse staff use the Inventory Scanner App (§3.28) to scan barcodes and enter counts on handheld devices, syncing to the active session in real time.

**Reorder & Safety Stock**

- **Reorder Point per Variant per Branch:** Configurable reorder point and safety stock quantity. When `quantity_on_hand` falls to or below reorder point, a `LowStockAlert` event is dispatched.
- **Lead Time Configuration:** Expected lead time in days per supplier-product combination, stored on `supplier_product_prices`.
- **Auto Reorder Suggestion:** When reorder is triggered, suggested PO quantity = (average daily sales × lead time) + safety stock − quantity_on_hand. A draft PO is auto-created against the preferred supplier and routed through the approval workflow.

**Quarantine Stock**

- Items received but pending quality inspection are placed in quarantine status. Quarantine stock is excluded from available-to-sell quantity until released by an authorized user. Status transitions: quarantine → released (available) or quarantine → scrapped.

#### 3.7. Point of Sale (POS) Interface

- **Speed-First Design:** A single-page, keyboard-navigable interface. Product search triggers on the first keystroke with a debounced API call returning results in under 200ms (p95).
- **Cashier Workspace:** Ability to hold multiple open carts concurrently. A visual indicator for a cart with a "suspended" status.
- **Real-Time Cart Validation:** On adding an item, the server instantly validates current stock levels and customer credit limits via WebSocket, showing warnings inline.

#### 3.8. Checkout, Payments & Invoicing

- **Unified Payment Screen:** Supports Cash, Credit/Debit Card, Mobile Wallets (Stripe, JazzCash, EasyPaisa), Bank Transfer, Gift Card, Store Credit, and Loyalty Wallet in a single transaction.
- **Split Tender:** A transaction can be settled with multiple payment methods simultaneously.
- **Layaway/Deposits:** Ability to take a partial payment and leave a balance for future fulfillment.
- **Credit Sales:** Directly convert a sale to a customer's credit account, subject to their credit limit. Feeds AR aging automatically.
- **Dynamic Invoicing:** Thermal (80mm) and A4/Letter templates via Laravel DomPDF. Invoices shareable via link, Email, or WhatsApp API.
- **Fiscal Provider Abstraction:** A `FiscalProviderInterface` with concrete implementations (`FBRProvider`, `ZATCAProvider`, `DummyProvider`) ensures no fiscal logic is hard-coded.
- **Offline Fiscal Queue:** When the fiscal API is unreachable, invoices are queued and auto-retried with exponential back-off.
- **Fiscal Compliance Fields:** NTN & STRN stored per branch; digital signature/QR code for fiscal invoices.

#### 3.9. Customer Relationship & Loyalty Management

- **360° Customer View:** A profile consolidating transaction history, average basket size, preferred payment method, loyalty tier, wallet balance, open credit balance, and AR aging status.
- **Tiered Loyalty Engine:** Define membership tiers (Silver, Gold, Platinum) with configurable point-earning rules and automated tier upgrades/downgrades.
- **Wallets & Store Credit:** Customers can have a stored value balance used for purchases, topped up manually or through refunds.

**Accounts Receivable Aging** *(New in v4.0)*

- **AR Aging Report:** Outstanding customer balances bucketed by age: Current, 1–30 days, 31–60 days, 61–90 days, 90+ days. Filterable by branch, customer group, and salesperson.
- **Customer Statement:** Generate a dated statement per customer showing all invoices, payments, credit notes, and running balance. Exportable as PDF and emailable directly from the system.
- **Overdue Reminder Workflow:** Configurable automated reminders dispatched at N days overdue (e.g., day 7, day 30, day 60) via SMS, email, or WhatsApp. Each reminder is logged against the customer.
- **Credit Limit Enforcement:** Credit sales are blocked at POS if the customer's outstanding AR balance plus the new sale exceeds their credit limit. Branch Manager PIN can override.
- **Bad Debt Write-Off:** Authorized users can write off irrecoverable debts. Posts a journal debit to Bad Debt Expense and credit to Accounts Receivable, with mandatory reason code and manager approval.
- **`ar_aging_snapshots` table:** `date`, `customer_id`, `branch_id`, `current`, `bucket_30`, `bucket_60`, `bucket_90`, `bucket_over_90`, `total_outstanding`. Populated nightly by ETL for historical trending.

#### 3.10. Supplier & Purchase Order Management

- **Full Procurement Cycle:** Create PO → Send to Supplier → GRN against PO → 3-Way Match → Supplier Invoice → Payment.
- **Purchase Approval Workflows:** POs over a configurable amount require Branch Manager or Owner approval. Escalation after N hours if approver is unresponsive (configurable per branch).
- **Dynamic Ledger:** A running payable/receivable ledger for each supplier showing invoices, payments, debit notes, and net balance.

**3-Way PO Matching** *(New in v4.0)*

- The three documents matched: Purchase Order (committed quantities and agreed prices), Goods Receiving Note (actual quantities received), Supplier Invoice (amounts billed).
- **Match Process:** When a supplier invoice is entered, the system automatically compares invoice lines to GRN lines to PO lines. Tolerances are configurable (e.g., ±2% price, ±0 qty by default).
- **Match Statuses:** `fully_matched`, `partially_matched`, `unmatched`.
- Only fully matched invoices can be approved for payment. Partial or unmatched invoices are flagged and routed to the Procurement Officer for resolution.
- **`po_match_results` table:** `purchase_order_id`, `grn_id`, `supplier_invoice_id`, `match_status`, `qty_variance`, `price_variance`, `matched_by`, `matched_at`, `exception_reason`.

**Supplier Price Lists & Contracts** *(New in v4.0)*

- **`supplier_price_lists` table:** `supplier_id`, `name`, `valid_from`, `valid_to`, `currency_code`.
- **`supplier_price_list_items` table:** `price_list_id`, `product_variant_id`, `unit_price`, `min_qty` (tiered pricing), `lead_time_days`.
- When a PO line is created, the system auto-populates unit price from the active supplier price list. Buyer can override with mandatory reason.
- Price list expiry alerts notify admin N days before expiry.

**Purchase Return & Return Merchandise Authorization (RMA)** *(New in v4.0)*

- **RMA Initiation:** Initiated from a posted GRN. User selects GRN lines, quantities to return, and return reason.
- **RMA Statuses:** `draft` → `approved` → `goods_dispatched` → `supplier_acknowledged` → `debit_note_issued` → `closed`.
- **Debit Note:** Formal numbered document posted against supplier ledger, reducing outstanding payable balance.
- **Stock Movement:** On `goods_dispatched`, immutable `stock_movement` with `reason = return_supplier`.
- **`purchase_returns` / `purchase_return_items` tables** track full RMA lifecycle.

**Landed Cost Allocation** *(New in v4.0)*

- **Landed Cost Components:** Freight, customs duty, insurance, port handling, inland transport, and other configurable cost types.
- **Allocation Methods:** Per GRN — by quantity, weight, value, or manual per line.
- **`landed_cost_entries` / `landed_cost_allocations` tables** update effective unit cost in FIFO/WAC calculations and feed `inventory_cost_layers`.

**Drop-Shipping** *(New in v4.0)*

- A PO can be flagged `drop_ship = true`, associating it with a customer sale order.
- Virtual stock movement recorded without updating on-hand warehouse quantities.
- GRN confirmation triggers customer invoice and shipment notification automatically.

#### 3.11. Accounting & Financial Management

- **Double-Entry Foundation:** `chart_of_accounts` defines the entire financial structure. Configurable automatic journal entry posting rules.
- **Reconciliation Tools:** Match imported bank statements against system entries for bank accounts.
- **Financial Statements:** Real-time Balance Sheet, Trial Balance, and P&L for any date range.
- **Tax Ledger Posting:** Dedicated GL accounts for each tax type; tax collected and tax payable tracked separately.
- **Cost Centres:** Optional dimension on every journal line for departmental/project P&L slicing.
- **Annual Budgeting:** Budgets per COA account per period; variance reporting against actuals.
- **Fiscal Year Close:** Supervised close procedure that locks historical periods, rolls retained earnings, and resets expense/revenue accounts.
- **Petty Cash Module:** Top-up vouchers, expense disbursements, and reconciliation.
- **Bank Transfers & Cheque Management:** Inter-account fund transfers; cheque issuance log with cleared/bounced status tracking.
- **Asset Register & Depreciation:** Fixed asset records with straight-line depreciation schedule — posts monthly depreciation journal automatically.

**COGS Posting Specification** *(New in v4.0)*

- **Posting Timing:** COGS posted in real-time at sale completion, not end-of-day batch.
- **Valuation Method:** FIFO and Weighted Average Cost (WAC) per product category or globally (§3.25). LIFO report-only (not GL posting).
- **`inventory_cost_layers` table:** Authoritative FIFO stack — `product_variant_id`, `warehouse_id`, `batch_no`, `received_at`, `qty_remaining`, `unit_cost`, `valuation_method`.
- **GL Entries on Sale:** Debit COGS, Credit Inventory (at resolved cost). Revenue entry posted separately.
- **Returns Reversal:** Restocked items restore cost layer at original sale cost; scrapped items post to Scrapped Goods Expense.

**Inter-Company & Inter-Branch Accounting** *(New in v4.0)*

- On stock transfer confirmation, auto-post intercompany journal entries at FIFO/WAC cost.
- **`intercompany_transactions` table:** `transfer_id`, `source_branch_id`, `dest_branch_id`, `amount`, `currency_code`, journal entry refs, `settled_at`.
- Head Office runs periodic intercompany settlement netting Due From/Due To balances.

**Multi-Currency Support** *(New in v4.0)*

- **`currencies` / `exchange_rates` tables.** Single functional currency per tenant; all GL balances in functional currency.
- Transactions in any configured currency; both original and functional-currency amounts stored.
- Period-end FX revaluation and realized FX gain/loss on settlement.

**Credit Note & Debit Note Document Management** *(New in v4.0)*

- **`credit_notes`:** Issued to customers for overcharges, returns, or goodwill adjustments. Reduces AR balance.
- **`debit_notes`:** Issued to suppliers for purchase returns or pricing disputes. Reduces AP balance.
- Both printable/emailable, reference originating invoice, tracked in `audit_logs`.

#### 3.12. Expense Management

- **Recurring Expenses:** Define rent, salary, or utility expenses to auto-generate journal entries on a schedule.
- **Digital Vault:** Attach scanned receipts or PDFs directly to expense entries.
- **Approval Workflow:** Expenses above a configurable threshold require manager approval before posting to the GL.

#### 3.13. Human Resources & Payroll

- **Attendance via POS:** Cashiers clock in/out using PIN on the POS terminal, creating a shift record linked to their employee profile.
- **Payroll Processing:** Generate payroll based on clocked hours and predefined salary structures, with ability to post payroll as a journal entry.

**Leave Management** *(New in v4.0)*

- Configurable leave types: Annual, Sick, Unpaid, Maternity/Paternity, Public Holiday, Compensatory.
- **`leave_entitlements` / `leave_requests` tables.** Request workflow: Employee → Line Manager → HR.
- Leave calendar, carry-forward policies, payroll integration for unpaid leave deductions.

**Overtime Engine** *(New in v4.0)*

- Overtime triggers: daily threshold (default 8h), weekly threshold (default 48h), public holiday/rest day work.
- Configurable rate multipliers: weekday 1.5×, weekend 2.0×, holiday 2.5×.
- **`overtime_records` table.** Branch Manager approval required before payroll inclusion.

**Payslip Generation & Employee Self-Service** *(New in v4.0)*

- Formatted payslip PDF per `payroll_item` with gross, deductions, net pay, YTD totals.
- Employee portal / mobile app: payslips, attendance, leave balance, leave requests, shift schedule.
- Automatic email delivery on payroll confirmation.

#### 3.14. Advanced Reporting & Analytics

- **Inventory Valuation:** FIFO, LIFO (report-only), or Weighted Average method.
- **Cashier Performance:** Sales per cashier, ATV, transaction count per shift.
- **Dynamic Report Builder:** Select dimensions and metrics; export to Excel and PDF.
- **AR Aging Report:** Customer outstanding balances by age bucket (§3.9).
- **AP Aging Report:** Supplier outstanding payables by age bucket; triggers payment run suggestions.
- **Inventory Turnover Report:** Units sold / average on-hand per period.
- **Supplier Performance Report:** Lead time vs. contracted, fill rate, return rate per supplier.

#### 3.15. Notification Engine

- **Configurable Channels:** Users configure preferences per event (email, SMS, push, WhatsApp).
- **System-Wide Notifications:** Admin can broadcast maintenance alerts to all POS terminals in a branch.
- **Escalation Rules:** If a notification is not acknowledged within N minutes, it escalates to the next role in the hierarchy.

#### 3.16. Refund, Return & Exchange Management — Customer Side

- **Policy-Driven Logic:** Configurable return window (e.g., 30 days) checked against original sale date.
- **Multi-Mode Return:** Refund to store credit, original payment method, or exchange within one workflow.
- **Manager Approval:** Refunds over a configurable threshold require manager PIN approval.

**Return Reason Codes** *(New in v4.0)*

- Configurable reason codes (Defective, Wrong Item, Changed Mind, Size/Fit Issue, etc.).
- Mandatory at POS; stored on return record; feeds return analytics and defect-rate reporting.

**Restocking & Condition Grading** *(New in v4.0)*

- **Disposition outcomes:** Resalable (return to inventory at original cost), Damaged/Defective (quarantine zone; Inventory Write-Down), or Scrapped (Scrapped Goods Expense; no stock movement).
- **Bundle Return Policy:** Pro-rata return amount for single bundle component; full bundle return option.

#### 3.17. Tax Configuration Engine

- **Composite Tax Groups:** Multiple tax rates applied as one line item but reported separately.
- **Inclusive/Exclusive Toggle:** Defined at product or customer group level.

**Withholding Tax (WHT)** *(New in v4.0)*

- **`wht_rates` table:** `supplier_category`, `applicable_section` (e.g., 153, 165), `rate_percent`, `effective_from`.
- Auto-computed WHT deduction on supplier payment; net payment and WHT Payable liability posted.
- Section 165 data exportable for FBR filing.

**Tax Return Export** *(New in v4.0)*

- Sales tax return data (taxable/exempt sales, output/input tax, net payable) in FBR-compliant XML/CSV.
- Input/output tax reconciliation report.
- WHT return data for Section 153/165 filing.
- ZATCA/UAE VAT stubs via `FiscalProviderInterface`.

#### 3.18. Data Import, Export & Migration

Retailers migrating from spreadsheets or another POS need **bulk operations** and controlled **historical data** loading.

**Shared import/export platform:**

- **Formats:** CSV (UTF-8) and Excel (`.xlsx`); downloadable templates.
- **Workflow:** Upload → validate (dry-run) → review errors → confirm → background job for files over 500 rows.
- **Idempotency:** `create`, `update`, and `upsert` modes.
- **Safety:** Row-level error report; strict mode option.
- **Audit:** Each run records `user_id`, `entity_type`, `mode`, `file_hash`, row counts in `import_export_jobs`.

| Entity | Import | Export | Notes |
| :--- | :---: | :---: | :--- |
| Categories, brands, units | Yes | Yes | Reference data; import before products. |
| Products & variants | Yes | Yes | Optional branch price columns. |
| Opening stock (per warehouse/bin) | Yes | Yes | Sets `quantity_on_hand` per bin via opening-balance movement. |
| Stock adjustments (bulk) | Yes | No | Manager-approved bulk corrections. |
| Customers | Yes | Yes | Optional loyalty tier, credit limit, opening wallet balance. |
| Suppliers + price lists | Yes | Yes | Contact, payment terms, contracted prices. |
| Users (staff) | Yes | No | Admin-only; invite/set password flow. |
| Chart of accounts | Yes | Yes | Optional seed from template. |
| Opening journal balances | Yes | No | Accountant-only. |
| Exchange rates (bulk) | Yes | Yes | Historical and current rates. |
| Historical sales (archive) | Yes | Yes | Read-only for analytics; does not deduct live stock. |
| Historical purchases | Yes | No | Supplier spend reports only. |
| WHT deduction data | No | Yes | For Section 165 FBR filing. |

**Historical & migration data rules:**

- **`is_historical` flag** on imported sales/purchases; excluded from live dashboards, inventory, and GL by default.
- **Go-live cutover:** Operator sets opening balance date; live POS transactions on or after cutover.
- **No double-counting:** Historical sale import must not reduce current stock.

#### 3.19. Restaurant Management Module

Enabled per-branch via the Module Config Engine (§3.24).

- Floor layout designer, table statuses (`available`, `occupied`, `reserved`, `cleaning`), table merging.
- Order types: Dine-in, Takeaway, Delivery, Drive-thru.
- KOT lifecycle: `pending` → `preparing` → `ready` → `served`. Multiple kitchen stations.
- Modifiers & combos, split billing, service charge, reservations, delivery rider workflow.

#### 3.20. Shift & Cash Register Management

Every POS terminal is tied to a named register. Cashiers must open a shift before making sales.

- **Shift Lifecycle:** Open → Mid-shift X-Report → Close. Blind close option. Manager PIN if variance exceeds threshold.
- **Cash Tracking:** `shift_cash_movements` logs paid-in/paid-out and no-sale drawer-open events.
- **X-Report / Z-Report:** Mid-shift and end-of-shift summaries; Z-report triggers shift close.

#### 3.21. Advanced Pricing & Promotions Engine

- Multiple named price lists scoped to branch or customer group; scheduled pricing via `valid_from`/`valid_to`.
- **Price Resolution Order:** (1) Customer-group price list, (2) Branch price list, (3) Branch product price override, (4) Variant base price.
- **Promotion Types:** BOGO, Bundle, Cart Discount, Category Discount. Exclusive or stackable.
- **Coupon System:** Unique codes with usage limits and expiry; bulk generation.

#### 3.22. Hardware Integration Layer

- ESC/POS receipt and kitchen printers (network/USB/serial).
- Cash drawer on cash payment or no-sale (logged).
- USB HID barcode scanners; browser camera scanning via quagga2.
- Web Serial API for weighing scales; customer-facing display; card terminal stubs.

#### 3.23. Recipe & Ingredient Management

- Raw materials, recipes linked to `product_variant_id`, BOM service, auto-deduct on sale, production batches.

#### 3.24. Modular Feature Management & Subscription Architecture

- **Module Registry:** `modules`, `module_features`, `tenant_modules`, `role_module_permissions`, `feature_flags`.
- **Dynamic Menu Rendering:** Sidebar from enabled modules × user permissions; `CheckModuleEnabled` middleware.
- **Subscription Plans:**

| Plan | Included Modules |
| :--- | :--- |
| Starter | POS Core, Inventory, Customers |
| Retail Pro | + Procurement, Accounting, Expenses, Reporting, WHT, AR Aging |
| Restaurant Pro | Starter + Restaurant, KDS, Recipe, Delivery |
| Enterprise | All modules including Multi-Currency, Leave, RMA, Cycle Count, Bin Locations |

#### 3.25. Global Configuration Engine

**4-Tier Resolution Hierarchy:**

```
System defaults
  → Tenant overrides
    → Branch overrides
      → User overrides  (UI preferences only)
```

**Config Categories:** `pos`, `tax`, `checkout`, `fbr`, `inventory`, `hr`, `restaurant`, `notifications`, `wht`, `multi_currency`, `cycle_count`.

- Settings changes take effect on next request.
- Encrypted storage for secrets; all changes logged to `audit_logs`.

#### 3.26. Gift Cards, Store Credits & Loyalty Wallet Enhancements

- Gift cards: physical/digital issuance, partial redemption, public balance enquiry API.
- Store credits: auto-issued on return; applied as payment method.
- Loyalty wallet: top-up via cash/card/bank transfer; configurable expiry policy.

#### 3.27. E-Commerce & Omni-Channel Integration

- Shopify / WooCommerce: product sync, inventory push, order pull.
- Daraz / TikTok Shop stubs with identical architecture.
- Unified customer profile; loyalty points on online orders.
- `integration_configs` with encrypted credentials and sync jobs.

#### 3.28. Mobile Applications

Four dedicated mobile apps (plus Employee App) built on React Native, authenticated via Sanctum mobile tokens.

| App | Primary Users | Key Features |
| :--- | :--- | :--- |
| **Customer App** | End customers | Loyalty balance, receipts, wallet top-up, store locator |
| **Waiter App** | Restaurant waiters | Table assignment, order taking, KOT status, bill request |
| **Inventory Scanner App** | Stock room staff | GRN receive, cycle count entry, bin transfer, transfer confirmation |
| **Manager Dashboard App** | Branch managers | Live KPIs, low stock alerts, pending approvals, shift summary, leave requests |
| **Employee App** | All staff | Payslip view, attendance history, leave requests, leave balance |

- All apps consume `/api/v1/` endpoints; mobile-specific routes under `/api/v1/mobile/`.
- Push notifications via Firebase FCM.

#### 3.29. Business Intelligence & Data Warehouse Readiness

- **`data_mart_sales`:** Daily grain — date, branch, variant, customer, quantity, revenue, cost, gross_profit, tax_collected.
- **`data_mart_inventory`:** Daily grain — opening/closing qty, net movement, value_fifo.
- **`data_mart_ar_aging`:** Daily AR aging buckets per customer (§3.9).
- Dedicated read-only MySQL user for external BI tools; Power BI / Tableau template files.
- Scheduled report delivery via cron (CSV/PDF email).
- **AI Demand Forecast Stub:** Mart data exported to configurable external ML endpoint; forecasts in `demand_forecasts`.

**Future Scope — AI & Predictive Analytics** *(deferred)*

Architecturally prepared via mart tables and structured data pipeline. Full implementation deferred until sufficient data exists:

- Demand forecasting, auto reorder suggestions, RFM segmentation, dynamic pricing recommendations, fraud detection signals, natural language report builder.

#### 3.30. Workflow Engine *(New in v4.0 — full specification)*

A configurable rules engine that automates multi-step approval and notification processes without code changes.

- **Trigger Events:** `po.created`, `po.approved`, `grn.received`, `sale.credit_limit_exceeded`, `return.initiated`, `expense.submitted`, `leave_request.submitted`, `supplier_invoice.pending_match`, `stock.below_reorder`, and any system event.
- **`workflow_definitions`:** `name`, `trigger_event`, `conditions_json`, `steps_json`.
- **Step Action Types:** `send_notification`, `require_approval`, `update_field`, `create_record`, `webhook`.
- **SLA / Escalation:** Configurable SLA per approval step; auto-escalation to next role if not actioned in time.
- **`workflow_instances` / `workflow_step_logs` tables.**
- **Admin UI:** Drag-and-drop workflow builder; running instances, history, SLA violations dashboard.

#### 3.31. Document Vault & Attachment Management *(New in v4.0)*

General-purpose document attachment system for all major entities.

- **Supported Entities:** Products, Suppliers, Customers, POs, GRNs, Supplier Invoices, Employees, Expenses, Sales, Returns, Contracts.
- **`document_attachments` table:** Polymorphic `entity_type`/`entity_id`, `file_name`, `file_path`, `mime_type`, `document_category`, `notes`.
- **Storage:** Laravel Storage abstraction (S3-compatible in production); signed temporary URLs on demand.
- **Access Control:** RBAC-governed; configurable document categories per entity type.
- **Retention Policy:** Configurable per category; flags documents past retention date for deletion.

### 4. Non-Functional & Operational Requirements

#### 4.1. Real-Time Communication

Laravel Reverb WebSocket channels:

- `pos.sale.completed.{branchId}`
- `inventory.stock.changed.{productId}`
- `system.notification.{userId}`
- `workflow.approval_required.{userId}`
- `ar.overdue_alert.{branchId}`

#### 4.2. Offline Resilience & Synchronization

- **Local-First Queue:** React POS uses IndexedDB for `offline_sales`; service worker syncs to server.
- **Conflict Resolution:** Out-of-stock on sync flags sale for manual cashier resolution.

#### 4.3. Audit & Compliance Logging

- **Immutable Log:** `audit_logs` records full change history; non-prunable for compliance.
- Financial journals once posted are immutable; corrections via reversal entries only.

#### 4.4. Security Architecture

- Depth in defense: CSRF, XSS, SQLi protections active.
- API rate limiting per-user and per-IP; account lockout after 5 failed logins; per-token quota for external API consumers.
- Encryption at rest for sensitive fields.
- Row-level security via branch-scoped Eloquent scopes.
- IP/geo restrictions, device binding for POS terminals, session expiry policies, password complexity, data retention, backup policies.

**GDPR & Data Privacy Controls (Stub)** *(New in v4.0)*

- Right to erasure (anonymize PII, retain transactions).
- `customer_consents` table for marketing/profiling/data-sharing consent.
- Customer data export in JSON/CSV. Full implementation deferred; schema prepared from initial build.

#### 4.5. API-First Design & Integration

- Comprehensive RESTful API behind `/api/v1/`, authenticated via Sanctum.
- Webhook system for core events (`order.created`, `refund.processed`, `po.approved`, etc.).
- Per-token API quota limits configurable in Admin → API Management.

#### 4.6. Performance, Caching & Scalability

- Redis caching with surgical cache tag invalidation.
- DB index audit and `EXPLAIN` checklist.
- Dedicated Redis queues via Supervisor.
- **Scale Targets:** 10,000 concurrent POS sessions; 1M variants; 100 TPS peak; POS search < 200ms p95.
- CQRS read models / pre-aggregated mart tables for reporting.
- Stateless application tier; all drivers externalized via Redis.

#### 4.7. Frontend User Experience & Accessibility

- shadcn/ui for WCAG 2.1 AA compliance.
- Contextual row actions; global command palette (⌘K / CTRL+K).

#### 4.8. Future-Proof Architecture & Extensibility

- SaaS multi-tenancy ready: nullable `tenant_id` on all tables.
- AI-ready data pipeline.
- Multi-language via Laravel localization and react-i18next.

#### 4.9. Disaster Recovery & Business Continuity *(New in v4.0)*

- **RTO:** Full restoration within 4 hours; POS-only mode within 1 hour.
- **RPO:** Maximum 1 hour data loss; backups every 60 minutes to geographically separate location.
- Nightly full snapshots + 60-minute incremental binlog shipping to S3-compatible storage.
- Monthly restore drills documented in runbook.
- MySQL Group Replication or RDS Multi-AZ failover.
- POS offline mode for up to 8 hours during complete server unavailability.

#### 4.10. Testing Strategy *(New in v4.0)*

- **Unit Tests:** 90%+ line coverage on Services, Repositories, DTOs (Pest); coverage gate in CI.
- **Integration Tests:** All API endpoints; E2E on checkout, COGS posting, GL journals, payroll, WHT deduction.
- **Accounting Invariant Tests:** Balanced double-entry on sale, return, purchase receive, payment, payroll, intercompany transfer, landed cost allocation.
- **UAT:** Product owner sign-off per phase acceptance criteria before production.
- **Load Testing:** 100 TPS for 30 minutes on staging; p95 POS search < 200ms with 1M variants / 100K customers.
- **Regression Suite:** CI blocks merge on failure; all bug fixes include regression tests.

### 5. Data Architecture & Database Design

The core transactional model is highly normalized. All tables include `created_at`, `updated_at`, `deleted_at` (soft delete), `tenant_id` (nullable), and monetary amounts as `DECIMAL(15,4)`.

| Schema Group | Tables |
| :--- | :--- |
| **Core & Auth** | `users`, `model_has_roles`, `role_has_permissions`, `user_permission_overrides`, `personal_access_tokens`, `customer_consents` |
| **Organisation** | `branches`, `warehouses`, `warehouse_zones`, `bin_locations`, `branch_user` |
| **Product Catalog (PIM)** | `products`, `product_variants`, `product_batches`, `product_serials`, `product_bundle_items`, `categories`, `brands`, `units`, `images`, `branch_product_prices`, `identifier_sequences` |
| **Inventory** | `inventories` (bin-level), `inventory_cost_layers`, `stock_movements`, `stock_transfers`, `stock_transfer_items`, `stock_reservations`, `count_sessions`, `count_session_lines` |
| **POS & Sales** | `pos_carts`, `pos_cart_items`, `pos_pin_lockouts`, `sales`, `sale_items`, `sale_payments`, `sale_invoices`, `sale_invoice_sequences`, `credit_notes` |
| **Fiscal** | `fiscal_invoices`, `fiscal_logs`, `fbr_invoice_sequences`, `fbr_invoice_queues`, `payment_gateway_configs` |
| **Customers & Loyalty** | `customers`, `customer_wallets`, `loyalty_points`, `loyalty_tiers`, `customer_groups`, `ar_aging_snapshots` |
| **Gift Cards & Store Credits** | `gift_cards`, `gift_card_transactions`, `store_credits` |
| **Procurement** | `purchase_orders`, `purchase_order_items`, `goods_receiving_notes`, `grn_items`, `suppliers`, `supplier_payments`, `supplier_price_lists`, `supplier_price_list_items`, `purchase_returns`, `purchase_return_items`, `po_match_results`, `landed_cost_entries`, `landed_cost_allocations`, `debit_notes` |
| **Accounting** | `chart_of_accounts`, `journal_entries`, `journal_transactions`, `cost_centres`, `budget_lines`, `bank_accounts`, `bank_statement_lines`, `cheques`, `fixed_assets`, `asset_depreciation_schedules`, `currencies`, `exchange_rates`, `intercompany_transactions`, `wht_rates` |
| **Expenses & HR** | `expense_categories`, `expense_entries`, `employees`, `employee_contracts`, `attendance_records`, `payroll_runs`, `payroll_items`, `leave_types`, `leave_entitlements`, `leave_requests`, `overtime_records` |
| **Shift & Register** | `registers`, `shifts`, `shift_cash_movements`, `no_sale_logs` |
| **Pricing & Promotions** | `price_lists`, `price_list_items`, `promotions`, `promotion_conditions`, `promotion_actions`, `coupons`, `coupon_usages` |
| **Returns** | `customer_returns`, `customer_return_items`, `return_reason_codes`, `rma_records`, `rma_items` |
| **Restaurant** | `floors`, `tables`, `table_orders`, `kot_tickets`, `kot_ticket_items`, `kitchen_stations`, `reservations`, `modifiers`, `modifier_groups` |
| **Raw Materials & Recipes** | `raw_materials`, `raw_material_movements`, `recipes`, `recipe_ingredients`, `production_batches` |
| **Hardware** | `printers`, `devices`, `printer_profiles` |
| **Notifications & Workflow** | `notifications`, `workflow_definitions`, `workflow_instances`, `workflow_step_logs` |
| **Reporting & BI** | `report_definitions`, `report_exports`, `data_mart_sales`, `data_mart_inventory`, `data_mart_ar_aging`, `demand_forecasts` |
| **Modular Feature Management** | `modules`, `module_features`, `tenant_modules`, `role_module_permissions`, `feature_flags`, `tenants`, `plans`, `subscriptions` |
| **E-Commerce & Integrations** | `integration_configs`, `integration_sync_logs` |
| **Documents** | `document_attachments` |
| **System** | `system_settings`, `import_export_jobs`, `import_validation_profiles`, `import_column_rules`, `import_row_errors`, `audit_logs` |

### 6. Third-Party & External Integrations

| Category | Integration | Notes |
| :--- | :--- | :--- |
| Payment Gateways | Stripe, PayPal, JazzCash, EasyPaisa | Unified payment abstraction layer. |
| Communication | Twilio (SMS), SendGrid/Mailgun (Email), WhatsApp Cloud API | Configurable per notification event. |
| Fiscalization | FBR IRIS (Pakistan), ZATCA (Saudi Arabia), UAE VAT stubs | `FiscalProviderInterface` per jurisdiction. |
| Accounting Connectors | Xero, QuickBooks (via API) | Mirror GL data externally. |
| E-Commerce | Shopify, WooCommerce, Daraz, TikTok Shop (stubs) | Product sync, inventory push, order pull. |
| Hardware | ESC/POS, Web HID, Web Serial, Verifone/Ingenico stubs | Hardware service layer. |
| Analytics & BI | Power BI, Tableau, Firebase FCM | Read-only DB user; `.pbix`/`.twb` templates. |
| Exchange Rates | Open Exchange Rates (or equivalent) | Fallback to manual entry. |
| Scanner SDKs | quagga2, Web Barcode Detection API | Mobile POS camera scanning. |
| Mobile | React Native + Firebase FCM + Sanctum | Five mobile apps (§3.28). |
| ML / AI | Configurable external ML endpoint | Demand forecast stub; `demand_forecasts` table. |

### 7. Deployment, DevOps & Infrastructure

- **CI/CD Pipeline:** lint → unit tests → integration tests → coverage check (minimum 90%) → build → staging → load test gate → production.
- **Containerization:** Docker for app tier, Redis, queue workers; Docker Compose for local dev.
- **Infrastructure:** Horizontally scalable app tier behind load balancer; Redis Cluster; MySQL 8.4 with Group Replication or RDS Multi-AZ.
- **Object Storage:** S3-compatible storage for documents, report exports, import files.
- **Monitoring & Alerting:** Telescope (dev); Sentry + Datadog (prod). Alerts on p95 > 500ms, queue depth > 1000, failed jobs > 10, replication lag > 30s.
- **Log Aggregation:** Structured JSON logs; 90 days hot, 2 years cold retention.
- **Secrets Management:** AWS Secrets Manager or HashiCorp Vault; never in version control.
- **RTO/RPO Compliance:** Infrastructure supports 4-hour RTO and 1-hour RPO (§4.9); monthly restore testing.
