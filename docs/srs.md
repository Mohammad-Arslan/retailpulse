Here is the improved and fully documented requirements specification, ready for your development team to start.

---

# Enterprise Point of Sale & Retail Management System - Requirements Specification

**Document Version:** 3.0
**Date:** 2026-06-04
**Status:** Living Document — Actively Extended

## Table of Contents
1.  **Introduction & Project Vision**
2.  **System Architecture & Technology Stack**
3.  **Core Functional Requirements**
    3.1. Authentication & Identity Management
    3.2. Authorization & Role-Based Access Control
    3.3. Dashboard & Real-Time Business Intelligence
    3.4. Multi-Branch & Centralized Management
    3.5. Product Information Management (PIM)
    3.6. Inventory & Warehouse Management
    3.7. Point of Sale (POS) Interface
    3.8. Checkout, Payments & Invoicing
    3.9. Customer Relationship & Loyalty Management
    3.10. Supplier & Purchase Order Management
    3.11. Accounting & Financial Management
    3.12. Expense Management
    3.13. Human Resources & Payroll
    3.14. Advanced Reporting & Analytics
    3.15. Notification Engine
    3.16. Refund, Return & Exchange Management
    3.17. Tax Configuration Engine
    3.18. Data Import, Export & Customer Onboarding
    3.19. Restaurant Management Module
    3.20. Shift & Cash Register Management
    3.21. Advanced Pricing & Promotions Engine
    3.22. Hardware Integration Layer
    3.23. Recipe & Ingredient Management
    3.24. Modular Feature Management & Subscription Architecture
    3.25. Global Configuration Engine
    3.26. Gift Cards, Store Credits & Loyalty Wallet Enhancements
    3.27. E-Commerce & Omni-Channel Integration
    3.28. Mobile Applications
    3.29. Business Intelligence & Data Warehouse Readiness
    3.30. AI & Predictive Analytics (Future Scope)
4.  **Non-Functional & Operational Requirements**
    4.1. Real-Time Communication
    4.2. Offline Resilience & Synchronization
    4.3. Audit & Compliance Logging
    4.4. Security Architecture
    4.5. API-First Design & Integration
    4.6. Performance, Caching & Scalability
    4.7. Frontend User Experience & Accessibility
    4.8. Future-Proof Architecture & Extensibility
5.  **Data Architecture & Database Design**
6.  **Third-Party & External Integrations**
7.  **Deployment, DevOps & Infrastructure**

---

### 1. Introduction & Project Vision
The goal is to build a **next-generation, enterprise-grade Point of Sale (POS) and Retail Management Ecosystem** designed for high-volume, multi-branch operations. The system will move beyond transactional processing to provide real-time operational command, AI-assisted insights, and an architecture designed for global SaaS expansion. It will serve as the central nervous system for retail businesses, unifying sales, inventory, finance, and human resources into a single, high-performance platform.

### 2. System Architecture & Technology Stack
The system will follow a **modular, domain-driven, API-first monolith** architecture, structured for a future migration to microservices. The codebase will enforce strict separation of concerns.

| Layer | Technology | Justification |
| :--- | :--- | :--- |
| **Backend Framework** | Laravel 13 | High-performance core with native queue, event, and real-time systems. |
| **Frontend** | React 19 with Inertia.js 2.0 | Seamless SPA-like UX without a separate API build step for the core app. |
| **UI Components & Styling** | shadcn/ui & Tailwind CSS 4 | Accessible, composable component primitives with a utility-first styling approach. |
| **Database** | MySQL 8.4 | Robust relational model for transactional integrity and complex reporting. |
| **Real-Time Engine** | Laravel Reverb over WebSockets | Scalable, server-pushed updates for dashboards, POS terminals, and stock levels. |
| **Auth & Permissions** | Laravel Breeze (Inertia + React), Sanctum (SPA/API) & Spatie Permission | Breeze provides session auth scaffolding; Sanctum for API tokens; Spatie for RBAC. |

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
- **Predefined Dynamic Roles:** A starter set of roles (Super Admin, Branch Manager, Cashier, etc.) that are fully editable.
- **Granular Permissions Manager:** An administrative UI for creating, grouping, and assigning permissions. Permissions can control UI element visibility (e.g., a "Show Cost Price" permission) and API access.
- **Advanced Role Management:** Features include:
    - **Role Cloning:** Create a new role by duplicating an existing one's permissions.
    - **Permission Inheritance:** A "Branch Manager" can inherit all permissions of a "Cashier" plus their own.
    - **User-Specific Overrides:** Grant temporary or permanent extra permissions to a single user without changing their core role.

#### 3.3. Dashboard & Real-Time Business Intelligence
- **KPI Widgets:** Configurable dashboard cards for Today's Sales, Gross Profit, Average Transaction Value, Low Stock Alerts, and Pending Approvals.
- **Real-Time Event Stream:** A live activity feed updated via Laravel Reverb showing high-value events: `New Sale: $452.00 by Cashier John at Branch X`, `Stock Alert: Product Y has reached reorder level`.
- **Comparative Charts:** Interactive charts (using a library like Tremor or Recharts) for Week-over-Week and Month-over-Month revenue, profit, and customer footfall, filterable by branch.

#### 3.4. Multi-Branch & Centralized Management
- **Head Office Console:** A central view for entity-level reporting and configuration management across all branches.
- **Branch Isolation with Sync:** Each branch has its own inventory quantities, pricing overrides, and tax configurations, but product master data can be managed centrally and synced down.
- **Operational Settings:** Define per-branch settings for currency, timezone, operating hours, default warehouse, and receipt footer.

#### 3.5. Product Information Management (PIM)
- **Rich Product Types:** Support for Standard, Variable (Size/Color), Service, Digital Download, Serialized (IMEI/VIN), and Combo/Bundle products.
- **Automated Identifiers:** System generates unique SKUs and Barcodes (EAN-13, UPC-A, CODE128) based on configurable patterns.
- **Advanced Inventory Tracking:** Track by Batch/Lot Number and Expiry Date (FEFO-first-expiry, first-out) or FIFO. Full audit trail for product master changes.

#### 3.6. Inventory & Warehouse Management
- **Multi-Warehouse Stock:** Real-time stock ledger with columns for `warehouse_id`, `product_variant_id`, `batch_no`, `expiry_date`, `quantity_on_hand`, and `quantity_reserved`.
- **Inventory Transactions:** Immutable stock movement log with reasons: `sale`, `purchase_receive`, `transfer_out`, `transfer_in`, `adjustment`, `damaged`, `return`.
- **Smart Workflows:** Stock transfers between branches/warehouses with a "shipped" and "received" confirmation status. Automatic prevention of negative stock sales.

#### 3.7. Point of Sale (POS) Interface
- **Speed-First Design:** A single-page, keyboard-navigable interface. Product search triggers on the first keystroke with a debounced API call.
- **Cashier Workspace:** Ability to hold multiple open carts concurrently. A visual indicator for a cart with a "suspended" status.
- **Real-Time Cart Validation:** On adding an item, the server instantly validates current stock levels and customer credit limits via WebSocket, showing warnings inline.

#### 3.8. Checkout, Payments & Invoicing
- **Unified Payment Screen:** Supports Cash, Credit/Debit Card, Mobile Wallets (Stripe, JazzCash), and Bank Transfer in a single transaction.
- **Complex Payment Handling:**
    - **Split Tender:** A $100 sale can be paid $60 by card and $40 in cash.
    - **Layaway/Deposits:** Ability to take a partial payment for a transaction and leave a balance for future fulfillment.
    - **Credit Sales:** Directly convert a sale to a customer's credit account, subject to their credit limit.
- **Dynamic Invoicing:** Thermal (80mm) and A4/Letter templates built with a templating engine (e.g., Laravel DomPDF). Invoices are generated server-side and can be instantly shared via a generated link, Email, or WhatsApp API.
- **Fiscal Provider Abstraction:** A `FiscalProviderInterface` with concrete implementations (`FBRProvider`, `ZATCAProvider`, `DummyProvider`) ensures no fiscal logic is hard-coded. New country-specific fiscal adapters can be registered without touching core checkout code.
- **Offline Fiscal Queue:** When the fiscal API is unreachable, invoices are queued and auto-retried with exponential back-off. Configurable failure mode: `queue` (sale proceeds) or `block` (sale held until fiscal confirmation).
- **Fiscal Compliance Fields:** NTN & STRN stored per branch; digital signature / QR code generation for fiscal invoices; separate `fiscal_invoices` and `fiscal_logs` tables for full request/response audit.

#### 3.9. Customer Relationship & Loyalty Management
- **360° Customer View:** A profile consolidating transaction history, average basket size, preferred payment method, loyalty tier, wallet balance, and open credit balance.
- **Tiered Loyalty Engine:** Define membership tiers (Silver, Gold, Platinum) with configurable point-earning rules (e.g., Gold members earn 1.5x points) and automated tier upgrades/downgrades.
- **Wallets & Store Credit:** Customers can have a stored value balance used for purchases, topped up manually or through refunds.

#### 3.10. Supplier & Purchase Order Management
- **Full Procurement Cycle:** Create Purchase Order (PO) -> Send to Supplier -> Goods Received Note (GRN) against PO -> Supplier Invoice against GRN -> Payment.
- **Purchase Approval Workflows:** POs over a configurable amount must be approved by a Branch Manager or Owner.
- **Dynamic Ledger:** A running payable/receivable ledger for each supplier showing invoices, payments, and debits from returns.

#### 3.11. Accounting & Financial Management
- **Double-Entry Foundation:** A `chart_of_accounts` table defines the entire financial structure. Configurable automatic journal entry posting rules. For example, a cash sale auto-posts a debit to `Cash` and a credit to `Sales Revenue`.
- **Reconciliation Tools:** Interface to match imported bank statements against system entries for accounts marked as "bank accounts."
- **Financial Statements:** Generate real-time Balance Sheet, Trial Balance, and Profit & Loss (P&L) reports for any date range.
- **Tax Ledger Posting:** Dedicated GL accounts for each tax type; tax collected and tax payable tracked separately for VAT/GST returns.
- **Cost Centres:** Optional dimension on every journal line for departmental/project P&L slicing without a separate COA.
- **Annual Budgeting:** Define budgets per COA account per period; variance reporting against actuals.
- **Fiscal Year Close:** A supervised close procedure that locks historical periods, rolls retained earnings, and resets expense/revenue accounts.
- **Petty Cash Module:** Petty cash register with top-up vouchers, expense disbursements, and reconciliation.
- **Bank Transfers & Cheque Management:** Inter-account fund transfers; cheque issuance log with cleared/bounced status tracking.
- **Asset Register & Depreciation (stub):** Fixed asset records with acquisition cost, useful life, and straight-line depreciation schedule — posts monthly depreciation journal automatically.

#### 3.12. Expense Management
- **Recurring Expenses:** Define rent, salary, or utility expenses to auto-generate journal entries on a schedule.
- **Digital Vault:** Attach scanned receipts or PDFs directly to expense entries.

#### 3.13. Human Resources & Payroll
- **Attendance via POS:** Cashiers clock in/out using their PIN on the POS terminal, creating a shift record linked to their employee profile.
- **Payroll Processing:** Generate payroll based on clocked hours and predefined salary structures, with the ability to post payroll as a journal entry.

#### 3.14. Advanced Reporting & Analytics
- **Inventory Valuation:** Report showing stock value based on `cost_price` using FIFO, LIFO, or Weighted Average method.
- **Cashier Performance:** Report on sales per cashier, average transaction value, and transaction count for a given shift.
- **Dynamic Report Builder:** Allow power users to select dimensions (e.g., product, branch, date) and metrics (e.g., total sales, profit) to build and save custom reports. All tabular data exports to Excel and PDF.

#### 3.15. Notification Engine
- **Configurable Channels:** Users can configure their notification preferences per event (e.g., "Notify me via email for low stock, but via push for new sales").
- **System-Wide Notifications:** An admin can send a maintenance alert to all POS terminals in a branch via a push broadcast.

#### 3.16. Refund, Return & Exchange Management
- **Policy-Driven Logic:** Configurable return window (e.g., 30 days) automatically checked against the original sale date.
- **Multi-Mode Return:** Refund to store credit, original payment method, or exchange for another product, all within one workflow. All refunds over $X require a manager's PIN approval.

#### 3.17. Tax Configuration Engine
- **Composite Tax Groups:** A "T-shirt Tax" group can include a 5% State Tax and a 2% City Tax, applied as one line item but reported separately.
- **Inclusive/Exclusive Toggle:** Ability to define at the product or customer group level whether prices are entered inclusive or exclusive of tax.

#### 3.18. Data Import, Export & Customer Onboarding
Retailers migrating from spreadsheets or another POS need **bulk operations** and controlled **historical data** loading—not only one-by-one CRUD. The system provides a shared import/export framework (CSV and Excel) with validation preview, queued processing for large files, downloadable templates, and full audit logging.

**Shared import/export platform (all modules):**
- **Formats:** CSV (UTF-8) and Excel (`.xlsx`) for import and export; downloadable **templates** with required columns and inline help.
- **Workflow:** Upload → validate (dry-run) → review errors/warnings → confirm → background job for files above a configurable row threshold (default 500 rows).
- **Idempotency:** Imports support `create`, `update`, and `upsert` (match on configurable key: SKU, barcode, email, supplier code, etc.).
- **Safety:** Row-level error report (downloadable); failed rows do not abort the entire batch unless the operator chooses strict mode. No silent overwrites of cost price or stock without explicit permission.
- **Audit:** Each import/export run records `user_id`, `entity_type`, `mode`, `file_hash`, row counts, and summary in `import_export_jobs` (linked to `audit_logs`).
- **Permissions:** `{module}.import`, `{module}.export` (e.g. `products.import`, `inventory.import-opening-stock`).
- **API:** Matching `/api/v1/.../import` and `/export` endpoints for integrators (Phase 15).

**Entity coverage:**

| Entity | Import | Export | Notes |
| :--- | :---: | :---: | :--- |
| Categories, brands, units | Yes | Yes | Reference data; import before products. |
| Products & variants | Yes | Yes | Standard and variable products; optional branch price columns. |
| Opening stock (per warehouse) | Yes | Yes | Sets `quantity_on_hand` via opening-balance movement; not a substitute for ongoing receive/adjust workflows. |
| Stock adjustments (bulk) | Yes | No | Manager-approved bulk corrections with reason code. |
| Customers | Yes | Yes | Optional loyalty tier, credit limit, opening wallet balance. |
| Suppliers | Yes | Yes | Contact and payment terms. |
| Users (staff) | Yes | No | Admin-only; invite/set password flow; no bulk password in file. |
| Chart of accounts | Yes | Yes | Optional seed from template; used before opening balances. |
| Opening journal balances | Yes | No | Accountant-only; posts opening entry per account (Phase 11). |
| Historical sales (archive) | Yes | Yes | **Read-only for analytics**; does not deduct live stock or post live journals unless explicitly enabled in strict migration mode. |
| Historical purchases | Yes | No | Optional; for supplier spend reports only when marked historical. |
| Bank statement lines | Yes | No | Already specified in §3.11 reconciliation. |
| Report result sets | No | Yes | Covered in §3.14 (Excel/PDF). |

**Historical & migration data rules:**
- **`is_historical` flag:** Imported sales, purchases, and related lines are stored with `is_historical = true` so dashboards, inventory, and GL posting rules exclude them by default.
- **Go-live cutover:** Operator sets an **opening balance date**; stock and accounting opening imports apply as of that date; live POS transactions must be on or after cutover.
- **Dashboards:** WoW/MoM and KPI widgets use live data only unless the user filters to "include historical imports."
- **No double-counting:** Historical sale import must not reduce current stock; opening stock import is the sole source of initial on-hand quantities.

**Out of scope (initial build):**
- Real-time bi-directional sync with external ERP (use API/webhooks in §4.5 instead).
- Import of raw payment card tokens (PCI).
- Fully automated AI mapping of unknown column layouts (manual template mapping only).

#### 3.19. Restaurant Management Module
A fully integrated restaurant layer that sits on top of the core POS. Enabled per-branch via the Module Config Engine (§3.24).

**Table & Floor Management**
- Floor layout designer: create named floors and place tables with capacity and position.
- Table statuses: `available`, `occupied`, `reserved`, `cleaning`.
- Table grouping/merging: combine adjacent tables for large parties.
- Live occupancy dashboard visible to host/waiter in real time via Reverb.

**Order Types**
- Dine-in (table-linked), Takeaway, Delivery, Drive-thru — each selectable at cart creation.

**Kitchen Order Ticket (KOT)**
- KOT generated on order confirmation and routed to the assigned kitchen station's printer or KDS screen.
- KOT lifecycle state machine: `pending` → `preparing` → `ready` → `served`.
- Multiple kitchen stations per branch (e.g., grill station, cold station), each with its own printer profile.

**Modifiers & Combos**
- Modifier groups (e.g., "Extras", "Cooking style") attached to product variants.
- Individual modifier items with optional price delta (e.g., "Extra cheese +$0.50").
- Combo meal builder: link variants into a bundle with a fixed combo price.

**Split Billing**
- Split bill by individual item assignment or equal split across N guests.
- Each guest sub-bill generates its own invoice and can be paid independently.

**Service Charge**
- Configurable service charge per branch: fixed amount or percentage; inclusive/exclusive of tax.
- Applied automatically to dine-in orders; waivable by manager.

**Reservation System**
- `reservations` table: guest name, phone, cover count, date/time, table assignment, status.
- Reservation reminder notification dispatched N minutes before arrival.

**Delivery Integration**
- Rider assignment workflow: assign delivery orders to an internal rider or push to third-party stub (Foodpanda, Uber Eats).
- Delivery status: `assigned` → `picked_up` → `delivered`.

#### 3.20. Shift & Cash Register Management
Every POS terminal is tied to a named register. Cashiers must open a shift before making sales.

**Register**
- `registers` table: `branch_id`, `name`, `description`, `status` (`active`/`inactive`).
- A branch can have multiple registers (multiple POS lanes).

**Shift Lifecycle**
- Open shift: cashier declares opening cash balance; shift record created with `opened_at`.
- Mid-shift X-report: prints current sales summary without closing the shift.
- Close shift: cashier declares actual closing cash; system calculates variance against expected.
- Blind close option: cashier declares actual cash before seeing the expected amount (manager setting).
- Manager variance approval: if `|variance| > threshold` (configurable per branch), manager PIN required to accept the close.

**Cash Tracking**
- `shift_cash_movements`: every paid-in/paid-out and no-sale drawer-open logged with reason and user.
- No-sale drawer log: every drawer open without a sale is recorded and surfaces in the fraud report.

**Reports**
- X-report (mid-shift): cashier performance, payment method breakdown, cash in drawer.
- Z-report (end-of-shift): final totals, variance, and shift summary — triggers shift close.
- Both reports printable to receipt printer.

#### 3.21. Advanced Pricing & Promotions Engine
A layered pricing system that resolves the correct price for every line item at POS time.

**Price Lists**
- Multiple named price lists: `retail`, `wholesale`, `vip`, or custom.
- Price lists can be scoped to a branch or a customer group.
- `price_list_items`: per-variant price override with optional `valid_from`/`valid_to` dates for scheduled pricing (e.g., weekend sale, happy hour).

**Price Resolution Order**
1. Customer-group price list (if customer assigned to a group with a price list)
2. Branch price list (if branch has a default price list)
3. Branch product price override (§3.5)
4. Product variant base price

**Promotion Engine**
- Promotion types:
    - **BOGO:** Buy N get M free (or at % discount) for the same or a linked product.
    - **Bundle:** Fixed price when a defined set of variants is in the cart together.
    - **Cart Discount:** Flat or percentage discount applied to the entire cart total.
    - **Category Discount:** All products in a category discounted by a fixed % for the session.
- Conditions JSON: minimum quantity, minimum cart value, customer group, time-of-day, day-of-week, branch.
- Actions JSON: discount type (flat/percent), target (line/cart/category), free item SKU.
- Stacking rules: promotions can be set as exclusive (only the best applies) or stackable.

**Coupon System**
- `coupons` table: unique code, linked promotion, max uses, per-customer use limit, expiry date.
- Coupon entry at POS checkout; real-time validation against uses and expiry.
- Bulk coupon generation (export codes from a single promotion).

#### 3.22. Hardware Integration Layer
Abstracts physical POS hardware behind a consistent service interface so device changes don't affect business logic.

**Receipt & Kitchen Printers**
- `printers` table: `branch_id`, `name`, `type` (`receipt`/`kitchen`/`label`), `connection_type` (`network`/`usb`/`serial`), `config` JSON (IP address, port, vendor ID, product ID).
- ESC/POS command dispatch service; supports Star Micronics, Epson, and generic ESC/POS.
- Printer profile CRUD in Settings; test-print button.
- Auto-print receipt on sale completion (configurable); auto-print KOT to kitchen station.

**Cash Drawer**
- `devices` table: cash drawer linked to a printer profile.
- Drawer-open command sent automatically on: cash payment accepted, manual no-sale (with log entry).

**Barcode Scanner**
- USB HID scanners treated as keyboard wedge input — no driver needed.
- Browser-based camera scanning via `quagga2` / Web Barcode Detection API for mobile POS.

**Weighing Scales (Grocery/Mart)**
- Web Serial API stub: reads weight from a serial-connected scale and auto-populates the quantity field at POS.

**Customer-Facing Display**
- Secondary screen support: item list, subtotal, and promotional message shown on a secondary browser window or dedicated display.

**Card Terminals**
- Stub interface for bank-provided card terminal SDK integration (Verifone, Ingenico); real integration added per payment gateway.

#### 3.23. Recipe & Ingredient Management
For food & beverage businesses, product variants map to recipes that drive raw-material deductions.

**Raw Materials**
- `raw_materials`: name, unit, cost price, reorder point, current stock qty per branch.
- Raw material stock movements: receive, consume, adjust, waste.

**Recipes**
- `recipes`: linked to a `product_variant_id`; yield quantity (e.g., one "Cappuccino" recipe yields 1 cup).
- `recipe_ingredients`: raw material, quantity required, wastage percentage.
- BOM service: resolves ingredient deduction list for a given cart item qty.

**Consumption on Sale**
- On sale completion, `BOMService::deductForSale()` reduces raw material stock for each sold variant that has an active recipe.
- If a raw material goes below reorder point, a `RawMaterialLowStock` event is dispatched → notification to branch manager.

**Production Batches**
- Manual production entry: operator logs finished goods produced from raw materials (for pre-made items).
- Each production batch records raw materials consumed and finished goods added to inventory.

#### 3.24. Modular Feature Management & Subscription Architecture
The system is composed of independently toggleable modules. This enables SaaS subscription plans and prevents UI clutter for businesses that don't need every feature.

**Module Registry**
- `modules`: `slug`, `name`, `description`, `icon`, `is_core`, `category` (core/business/restaurant/enterprise/saas).
- `module_features`: granular features within a module (e.g., `restaurant.kot`, `inventory.batch_tracking`).
- Core modules (Auth, Settings, RBAC, POS Core) are always enabled and cannot be disabled.

**Tenant Module Assignment**
- `tenant_modules`: maps modules to tenants (or globally for single-tenant deployments); includes `is_enabled`, `expires_at`.
- `role_module_permissions`: per-role access to each module (view/create/update/delete).

**Feature Flags**
- `feature_flags`: key-value store with scope (`system`/`tenant`/`branch`); boolean or JSON value.
- Resolved at runtime by `FeatureFlagService::isEnabled($slug, $scopeEntity)`.
- Used for gradual rollouts, beta features, and tenant-specific customisation.

**Dynamic Menu Rendering**
- Sidebar navigation is generated server-side from the set of enabled modules × user permissions.
- A module that is disabled produces no menu items, no routes are registered, and its controllers return 404.
- `CheckModuleEnabled` middleware guards every module's routes.

**Subscription Plans (SaaS)**
- `plans`: name, billing cycle, price, features JSON (list of enabled module slugs).
- `subscriptions`: tenant → plan → starts_at → expires_at.
- Plan upgrade/downgrade triggers `tenant_modules` sync.

| Plan | Included Modules |
| :--- | :--- |
| Starter | POS Core, Inventory, Customers |
| Retail Pro | + Procurement, Accounting, Expenses, Reporting |
| Restaurant Pro | Starter + Restaurant, KDS, Recipe, Delivery |
| Enterprise | All modules |

#### 3.25. Global Configuration Engine
A hierarchical settings architecture that supersedes hard-coded constants throughout the application.

**4-Tier Resolution Hierarchy**
```
System defaults
  → Tenant overrides
    → Branch overrides
      → User overrides  (for UI preferences only)
```
Lower tiers shadow higher tiers; the resolved value is always the most specific applicable setting.

**Config Categories**
- `pos` — idle timeout, max carts, offline mode, cash drawer auto-open
- `tax` — enabled, mode (inclusive/exclusive), default rate, rounding strategy
- `checkout` — payment methods, layaway rules, invoice numbering, deduction timing
- `fbr` — provider, credentials, failure mode, sandbox/live toggle
- `inventory` — negative stock policy, FIFO/FEFO default, reorder notification threshold
- `hr` — clock-in method, payroll cycle, overtime policy
- `restaurant` — service charge, tip policy, KOT auto-print, table turn alert
- `notifications` — channel preferences, low-stock threshold, escalation delay

**Operational Requirements**
- Settings changes take effect on the next request (no deployment required).
- Encrypted storage for secrets (API keys, gateway credentials) using Laravel Encrypter.
- All settings changes logged to `audit_logs` with old/new values.
- Admin UI: tabbed settings editor grouped by category; per-branch override editor for Branch Managers.

#### 3.26. Gift Cards, Store Credits & Loyalty Wallet Enhancements
**Gift Cards**
- `gift_cards`: code (unique, alphanumeric), initial value, current balance, issued by, issued at, expiry date, status (`active`/`redeemed`/`expired`/`void`).
- Issuance: physical card (print barcode) or digital (email/WhatsApp QR code).
- Redemption at POS checkout as a payment method; partial redemption supported (remainder stays on card).
- Balance enquiry via public API endpoint (no auth required, code only).

**Store Credits**
- `store_credits`: linked to customer, amount, reason (return/promotion/manual), expiry, status.
- Auto-issued when a return is processed in store-credit mode.
- Applied as a payment method at POS; non-transferable by default.

**Loyalty Wallet Enhancements**
- Wallet top-up via cash, card, or bank transfer.
- Wallet balance visible in customer 360° profile and at POS during checkout.
- Expiry policy: wallet balances optionally expire after N days of inactivity.

#### 3.27. E-Commerce & Omni-Channel Integration
**Platform Connectors**
- Shopify: product sync (push catalogue → Shopify), inventory level push on stock change event, order pull (new Shopify orders create POS carts).
- WooCommerce: same pattern as Shopify via REST API.
- Daraz / TikTok Shop: stub connector; architecture identical, credentials configurable.

**Unified Customer Profile**
- Incoming orders matched to existing customers by email or phone; new customers auto-created with `source = shopify/woocommerce`.
- Loyalty points earned on online orders when integration is active.

**Integration Config**
- `integration_configs`: provider slug, branch scope, encrypted credentials, sync settings JSON, `last_sync_at`.
- Admin UI: Integration Settings page with connection test, sync status, and manual trigger.

**Sync Jobs**
- Product sync: scheduled (nightly by default) or triggered on product save.
- Inventory push: triggered by `InventoryStockChanged` event (real-time delta push).
- Order pull: polling job every N minutes or via webhook receiver.

#### 3.28. Mobile Applications
Four dedicated mobile apps built on React Native (recommended), authenticated via Sanctum mobile tokens with app-specific token scopes.

| App | Primary Users | Key Features |
| :--- | :--- | :--- |
| **Customer App** | End customers | Loyalty balance, point history, digital receipts, wallet top-up, store locator |
| **Waiter App** | Restaurant waiters | Table assignment, order taking, KOT status, bill request |
| **Inventory Scanner App** | Stock room staff | Barcode scan to GRN receive, stock count entry, transfer confirmation |
| **Manager Dashboard App** | Branch managers | Live KPI widgets, low stock alerts, pending approvals, shift summary |

**API Contract**
- All mobile apps consume `/api/v1/` endpoints; mobile-specific endpoints prefixed `/api/v1/mobile/`.
- Push notifications via Firebase FCM; device token registered on login.
- Offline support for Inventory Scanner App (IndexedDB queue synced on reconnect).

#### 3.29. Business Intelligence & Data Warehouse Readiness
**Pre-Built Data Marts**
- `data_mart_sales`: daily grain — `date`, `branch_id`, `product_variant_id`, `customer_id`, `quantity`, `revenue`, `cost`, `gross_profit`, `tax_collected`. Populated by nightly ETL job.
- `data_mart_inventory`: daily grain — `date`, `branch_id`, `product_variant_id`, `opening_qty`, `closing_qty`, `net_movement`, `value_fifo`.
- Mart tables are read-only from the application; written only by the ETL job.

**External BI Tools**
- Dedicated read-only MySQL user with access only to mart tables.
- Connection string generator in Admin → Integrations (generates credentials on demand).
- Pre-built Power BI / Tableau template files (`.pbix` / `.twb`) provided as downloadable assets.

**Scheduled Report Delivery**
- Admin can schedule any saved report definition to run on a cron and email the result as CSV/PDF to a configured recipient list.

**AI Demand Forecast (Stub)**
- Scheduled job exports mart data to a configurable external ML API endpoint.
- Forecast response (predicted demand per variant per branch for next N days) stored in `demand_forecasts` table.
- Displayed as an advisory widget on the Dashboard (Phase 27 implementation).

#### 3.30. AI & Predictive Analytics (Future Scope)
These capabilities are architecturally prepared in earlier phases but are scoped for later implementation once sufficient data exists.

- **Demand Forecasting:** Predict per-variant reorder quantities and optimal timing based on historical sales velocity and seasonality.
- **Auto Reorder Suggestions:** Surface suggested purchase order quantities to procurement when forecasted demand exceeds projected stock.
- **Customer Behaviour Analytics:** RFM (Recency, Frequency, Monetary) segmentation; churn prediction; personalised promotion targeting.
- **Dynamic Pricing Recommendations:** Suggest price adjustments based on margin targets, competitor signals, and demand elasticity.
- **Fraud Detection Signals:** Anomaly scoring on transactions (unusual discount rates, high-value voids, multiple payment reversals) surfaced in the audit dashboard.
- **Natural Language Report Builder:** Allow power users to describe a report in plain language ("Show me top 10 products by margin last month at Lahore branch") and generate the SQL/filter automatically.

### 4. Non-Functional & Operational Requirements

#### 4.1. Real-Time Communication
Laravel Reverb will use WebSocket channels (private, presence) to broadcast a strict, event-driven schema:
- `pos.sale.completed.{branchId}`
- `inventory.stock.changed.{productId}`
- `system.notification.{userId}`

#### 4.2. Offline Resilience & Synchronization
- **Local-First Queue:** The React POS interface uses IndexedDB to persist a queue of `offline_sales`. A background service worker attempts to sync them to the server.
- **Conflict Resolution:** On sync, if a sold product is now out of stock online, the offline sale is flagged, and the cashier is alerted to resolve it manually upon reconnection.

#### 4.3. Audit & Compliance Logging
- **Immutable Log:** The `audit_logs` table records `user_type`, `user_id`, `event`, `auditable_type`, `auditable_id`, `old_values` (JSON), `new_values` (JSON), `url`, `ip_address`, and `user_agent`. This data is non-prunable for compliance.

#### 4.4. Security Architecture
- **Depth in Defense:** All standard protections (CSRF, XSS, SQLi via Eloquent) are active.
- **API Security:** Rate limiting is applied per-user and per-IP. Suspicious activity (e.g., 5 failed logins) triggers an account lockout and security event.
- **Encryption at Rest:** Sensitive fields like customer credit card tokens or API keys are encrypted using Laravel's built-in encrypter.
- **Row-Level Security:** Branch-scoped queries enforced via global Eloquent scopes; cross-branch data access blocked at the repository layer even for high-privilege roles unless explicitly granted.
- **IP & Geo Restrictions:** Optional allowlist of IP ranges per branch or per user role; requests outside the allowlist trigger an MFA challenge or hard block.
- **Device Binding:** POS terminals can be registered as trusted devices; new devices require manager approval before accessing the POS.
- **Session Expiry Policies:** Configurable idle timeout (default 30 min for cashier, 120 min for admin); absolute session max-age (default 12 h); concurrent session limit per user.
- **Password Complexity Policies:** Minimum length, uppercase/lowercase/digit/symbol requirements; prevent reuse of last N passwords; configurable expiry period.
- **Data Retention Policies:** Configurable automated archival of `audit_logs`, `stock_movements`, and `notifications` older than N years to cold storage or export.
- **Backup Policies:** Scheduled database snapshots with configurable retention window; documented restore procedure as part of the operational runbook.

#### 4.5. API-First Design & Integration
- **Comprehensive RESTful API:** Every backend feature will have a corresponding, documented API endpoint behind the `/api/v1/` prefix, authenticated via Sanctum tokens.
- **Webhook System:** An admin panel to register external URLs that will receive POST requests for core system events (`order.created`, `refund.processed`), enabling real-time middleware integration.

#### 4.6. Performance, Caching & Scalability
- **Strategic Caching:** Configuration, role permissions, and product master data are cached in Redis for near-instant access. Cache tags allow for surgical invalidation (e.g., `products:{id}`).
- **Database Optimization:** Indexes on all foreign keys and frequently queried columns (`sku`, `barcode`, `slug`). `EXPLAIN` plan reviews will be part of the development checklist.
- **Queue Workers:** All non-transactional tasks (notifications, report generation, data export) are dispatched to dedicated Redis queues processed by Supervisor.
- **Explicit Scale Targets:** The system must sustain 10,000 concurrent POS sessions, a product catalog of 1M variants, 100 completed transactions/second at peak, and a POS product-search response time below 200 ms (p95).
- **CQRS Read Models:** For reporting-heavy paths (inventory valuation, sales analytics), dedicated read-optimised projections or pre-aggregated mart tables are used instead of live OLTP queries, preventing contention with transactional writes.
- **Horizontal Scaling Readiness:** Session, cache, and queue drivers must be externalisable (Redis); no local filesystem assumptions in application code (use Laravel Storage abstraction); application tier must be stateless.

#### 4.7. Frontend User Experience & Accessibility
- **Design System:** Built on shadcn/ui primitives for full accessibility compliance (WCAG 2.1 AA).
- **Contextual Actions:** Right-click on a data table row to reveal a context menu with actions (View, Edit, Delete).
- **Command Palette (⌘K/CTRL+K):** A global command palette for super-users to navigate to any page, find any product, or trigger an action.

#### 4.8. Future-Proof Architecture & Extensibility
- **SaaS Multi-Tenancy Ready:** A `tenant_id` column will be globally present but nullable in the initial single-tenant build, preparing the data model for future SaaS separation.
- **AI-Ready Data Pipeline:** System will record anonymized, structured sales and inventory data, making it directly consumable for future AI modules (demand forecasting, fraud detection) without schema changes.
- **Multi-Language (i18n):** All static strings in the frontend and backend will use Laravel's localization and a `react-i18next` equivalent, making the entire interface translatable.

### 5. Data Architecture & Database Design
The core transactional model will be highly normalized to ensure data integrity. Design the complete database schema with migrations and seeders. Key schema groups:

**Core & Auth**
- `users`, `model_has_roles`, `role_has_permissions`, `user_permission_overrides`, `personal_access_tokens`

**Organisation**
- `branches`, `warehouses`, `branch_user`

**Product Catalog (PIM)**
- `products`, `product_variants`, `product_batches`, `product_serials`, `product_bundle_items`, `categories`, `brands`, `units`, `images`, `branch_product_prices`, `identifier_sequences`

**Inventory**
- `inventories`, `stock_movements` (immutable), `stock_transfers`, `stock_transfer_items`, `stock_reservations`

**POS & Sales**
- `pos_carts`, `pos_cart_items`, `pos_pin_lockouts`, `sales`, `sale_items`, `sale_payments`, `sale_invoices`, `sale_invoice_sequences`

**Fiscal**
- `fiscal_invoices`, `fiscal_logs`, `fbr_invoice_sequences`, `fbr_invoice_queues`, `payment_gateway_configs`

**Customers & Loyalty**
- `customers`, `customer_wallets`, `loyalty_points`, `loyalty_tiers`, `customer_groups`

**Gift Cards & Store Credits**
- `gift_cards`, `gift_card_transactions`, `store_credits`

**Procurement**
- `purchase_orders`, `purchase_order_items`, `goods_receiving_notes`, `grn_items`, `suppliers`, `supplier_payments`

**Accounting**
- `chart_of_accounts`, `journal_entries`, `journal_transactions`, `cost_centres`, `budget_lines`, `bank_accounts`, `bank_statement_lines`, `cheques`, `fixed_assets`, `asset_depreciation_schedules`

**Expenses & HR**
- `expense_categories`, `expense_entries`, `employees`, `employee_contracts`, `attendance_records`, `payroll_runs`, `payroll_items`

**Shift & Register**
- `registers`, `shifts`, `shift_cash_movements`, `no_sale_logs`

**Pricing & Promotions**
- `price_lists`, `price_list_items`, `promotions`, `promotion_conditions`, `promotion_actions`, `coupons`, `coupon_usages`

**Restaurant**
- `floors`, `tables`, `table_orders`, `kot_tickets`, `kot_ticket_items`, `kitchen_stations`, `reservations`, `modifiers`, `modifier_groups`

**Raw Materials & Recipes**
- `raw_materials`, `raw_material_movements`, `recipes`, `recipe_ingredients`, `production_batches`

**Hardware**
- `printers`, `devices`, `printer_profiles`

**Notifications & Workflow**
- `notifications`, `workflow_definitions`, `workflow_instances`, `workflow_steps`

**Reporting & BI**
- `report_definitions`, `report_exports`, `data_mart_sales`, `data_mart_inventory`

**Modular Feature Management & SaaS**
- `modules`, `module_features`, `tenant_modules`, `role_module_permissions`, `feature_flags`, `tenants`, `plans`, `subscriptions`

**E-Commerce & Integrations**
- `integration_configs`, `integration_sync_logs`

**System**
- `system_settings`, `import_export_jobs`, `import_validation_profiles`, `import_column_rules`, `import_row_errors`, `audit_logs` (polymorphic)

### 6. Third-Party & External Integrations
- **Payment Gateways:** Stripe, PayPal, JazzCash, EasyPaisa.
- **Communication:** Twilio (SMS), SendGrid/Mailgun (Email), WhatsApp Cloud API.
- **Fiscalization & Accounting:** FBR IRIS (Pakistan), ZATCA (Saudi Arabia), UAE VAT stubs; Xero, QuickBooks connectors via API.
- **E-Commerce Platforms:** Shopify (product sync, order pull), WooCommerce, Daraz, TikTok Shop (stubs).
- **Hardware:** ESC/POS over network/USB for thermal printers; Web HID API for USB barcode scanners; Web Serial API for weighing scales; bank card terminal gateway stubs.
- **Analytics & BI:** Power BI / Tableau via dedicated read-only DB user and ODBC connection string; Firebase FCM for mobile push notifications.
- **Scanner SDKs:** Browser-based scanner libraries (e.g., `quagga2`) for mobile camera barcode scanning.
- **Mobile:** React Native (recommended) for customer, waiter, inventory scanner, and manager apps; authenticated via Sanctum mobile tokens.
