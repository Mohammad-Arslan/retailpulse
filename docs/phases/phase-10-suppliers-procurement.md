# Phase 10 — Supplier & Purchase Order Management

**SRS Reference:** §3.10, §3.18 (suppliers)  
**Depends on:** Phase 4, Phase 3 (warehouse CRUD follow-up recommended before multi-warehouse GRN)

## Status (gap closure — 2026-06-22)

| Area | Status | Notes |
|------|--------|-------|
| **Core cycle** (PO → GRN → invoice → payment → ledger) | ✅ Complete | Ledger posts on `fully_matched` only; idempotent post on match resolve |
| **Purchase Requests** | ✅ Complete | Internal demand: draft → submit → approve → convert to draft PO; PIN approval; Phase 29 strategy stub |
| **PO send gating** | ✅ Complete | PDF/email only when `approved` or `closed` (unless `is_historical`) |
| **3-way matching** | ✅ Complete | `ThreeWayMatchingService`, resolve on GRN/PO/reports |
| **v4.0 functional UI** | ✅ Complete | Manual landed cost, partial returns, GRN/price-list polish, badges, dashboard drill-down |
| **Backend polish** | ✅ Complete | Payment method toggles in Settings, thin policies, audit observers, RBAC |
| **i18n / ur locale** | ✅ Complete | PO/GRN/Dashboard/Supplier ledger wired; `ur.json` procurement parity |
| **Notification alerts** | 🔶 Stub | `procurement_alerts` table + jobs + dashboard strip; full delivery Phase 14 |
| **Phase 11 integration** | 🔶 Stubbed | `ProcurementPostingHook` + `NullProcurementPostingHook` for GL and FIFO cost layers |
| **Workflow approval (Phase 29)** | ⏸ Deferred | `WorkflowPoApprovalStrategy` / `WorkflowPrApprovalStrategy` disabled until workflow engine exists |
| **Report CSV/Excel exports** | ⏸ Deferred | P2 polish |
| **Historical PO bulk import** | ⏸ Deferred | `is_historical` column exists; import handler not built |

---

## Objective

Full **procurement cycle** with approval workflows and supplier ledger — including optional internal **Purchase Requests** that convert to Purchase Orders.

## Database (key tables)

- `suppliers` — contact, terms, balance
- `purchase_requests`, `purchase_request_items` — internal demand (nullable `tenant_id`; never sent to suppliers)
- `purchase_orders`, `purchase_order_items`
- `goods_receiving_notes`, `grn_items`
- `supplier_invoices`, `supplier_payments`
- `supplier_ledger_entries` — running payable balance
- `supplier_price_lists`, `supplier_price_list_items`
- `supplier_attachments`
- `landed_cost_entries`, `landed_cost_allocations`
- `purchase_returns`, `purchase_return_items`, `debit_notes`
- `po_match_results`, `supplier_performance_scores`
- `procurement_alerts` — in-app alert stubs for escalation and price-list expiry (Phase 14 email/SMS)

## Features

- **Purchase Request:** create → submit → approve (PIN if over `pr_approval_threshold`) → convert to draft PO (supplier required at convert). Statuses: `draft`, `submitted`, `approved`, `rejected`, `cancelled`, `converted`. Feature kill-switch: `feature_flags.procurement.purchase_requests` (interim until Phase 23 `procurement.purchase_request` module feature). Workflow stub: `feature_flags.procurement.pr_workflow_approval`.
- PO create → send (email/PDF) → approval if amount > threshold
- GRN against PO → updates inventory (stock movement `purchase_receive`) at selected **warehouse** (active warehouses for branch via Phase 3 warehouse CRUD)
- Supplier invoice against GRN
- Payment recording against invoice
- Approval: Branch Manager / Owner PIN or permission
- Permissions: `procurement.*`, `procurement.approve-po`, `procurement.approve-pr`, `procurement.convert-pr`
- **Bulk import/export (§3.18):** suppliers CSV/Excel; optional historical purchase headers with `is_historical` (reporting only); `suppliers.import`, `suppliers.export`
- UI strings: use i18n keys in **camelCase** with **Title Case** English values (see `.ai/rules/frontend.mdc`). Map dynamic dropdown values (status, payment method) through `resources/js/lib/procurementI18n.js` — never show raw `snake_case` from the API.

### Key implementation files

| Feature | Backend | Frontend |
|---------|---------|----------|
| Purchase Requests | `PurchaseRequestController`, `PurchaseRequestService`, `PrApprovalStrategyFactory` | `PurchaseRequests/*` |
| Suppliers + ledger | `SupplierController`, `SupplierService` | `Suppliers/*` |
| Attachments | `SupplierAttachmentController` | `Suppliers/Show.jsx` |
| Price lists | `SupplierPriceListController`, `SupplierPriceListService` | `SupplierPriceLists/*` |
| PO lifecycle | `PurchaseOrderController`, `PurchaseOrderService` | `PurchaseOrders/*` |
| GRN + landed cost | `GoodsReceivingNoteController`, `LandedCostController` | `GoodsReceivingNotes/*` |
| Returns + debit PDF | `PurchaseReturnController`, `DebitNotePdfService` | `GoodsReceivingNotes/Show.jsx` |
| Drop-ship | `DropShipService`, `DropShipGrnConfirmed` event | `PurchaseOrders/Create.jsx` |
| Phase 11 stubs | `ProcurementPostingHook`, `NullProcurementPostingHook` | — |
| Dashboard KPIs | `ProcurementDashboardService` | `Dashboard.jsx` |

## Acceptance Criteria

1. PO → GRN → Invoice → Payment flow updates stock and supplier balance correctly. ✅
2. PO over configured amount requires approval before send. ✅
3. Supplier list import creates 50+ suppliers with validation errors surfaced per row. ✅ (runtime; dedicated 50+ row test optional)
4. Approved Purchase Request converts to a draft PO with mapped lines; PR becomes `converted`. ✅
5. PR over `pr_approval_threshold` requires PIN (or Phase 29 workflow when flag on). ✅

### Phase 23 / 29 hooks

- **Phase 23:** feature slug `procurement.purchase_request` under module `procurement` (documented for future `ModuleSeeder` / `module_features`). Today gated by `SystemSetting` `feature_flags.procurement.purchase_requests`.
- **Phase 29:** `pr.approval` workflow definition; `WorkflowPrApprovalStrategy` is a stub until the engine ships (same pattern as PO).

---

## Phase Enhancements (SRS v4.0)

### Purchase Approval Workflow Hook (§3.30 — Phase 29)
- The existing approval threshold logic (PO amount > configurable limit requires manager PIN) is extended to optionally route through the generic Workflow Engine when Phase 29 is active.
- When `feature_flags.procurement.workflow_approval` is enabled, PO approval triggers a `WorkflowInstance` instead of a direct PIN prompt.
- Backwards compatible: when the feature flag is off, the existing PIN-based approval remains.
- PO escalation after N hours of no approver action (configurable per branch). ✅ `PoApprovalEscalationJob` (persists `procurement_alerts` + dashboard strip; email Phase 14)

### Supplier Performance Scoring
- `suppliers` table: `on_time_delivery_rate`, `quality_rejection_rate`, `last_scored_at`.
- Scheduled job (`SupplierPerformanceScoringJob`) calculates scores from GRN delivery dates vs PO expected dates and rejection notes.
- Scores displayed on Supplier show page with optional history table. ✅ Advisory only.

---

## SRS v4.0 Enhancements (§3.10)

### 3-Way PO Matching ✅

- **`po_match_results`** — auto-compare invoice → GRN → PO lines on supplier invoice entry.
- Configurable tolerances (default ±2% price, ±0 qty).
- Only `fully_matched` invoices approvable for payment; exceptions routed to Procurement Officer.
- Permission: `procurement.resolve-match-exception`.

### Supplier Price Lists & Contracts ✅

- CRUD under `admin/supplier-price-lists`.
- PO line auto-populates unit price from active price list; override requires reason.
- Expiry alert job (`PriceListExpiryAlertJob`) reads `price_list_expiry_alert_days`; persists `procurement_alerts` (full notification delivery Phase 14).
- Bulk import/export of price lists via `supplier-price-lists` entity (`SupplierPriceListImportHandler` / `SupplierPriceListExportHandler`).

### Purchase Return & RMA ✅

- Full lifecycle: `draft` → `approved` → `goods_dispatched` → `supplier_acknowledged` → `debit_note_issued` → `closed`.
- **`debit_notes`** — PDF download via `DebitNotePdfService`.
- Stock movement `return_supplier` on `goods_dispatched`.
- GL posting: **stubbed** via `ProcurementPostingHook::postPurchaseReturn()` until Phase 11.

### Landed Cost Allocation ✅ (UI + allocation; FIFO stub)

- Landed cost form on GRN show; weight allocation uses variant metadata weight.
- Allocation methods: quantity, weight, value, manual.
- FIFO layer update: **stubbed** via `ProcurementPostingHook::applyLandedCost()` until Phase 11.

### Drop-Shipping ✅ (partial — customer invoice deferred)

- `purchase_orders.drop_ship` + required `sale_id` on PO create.
- Virtual receive; no warehouse on-hand change.
- `DropShipGrnConfirmed` event dispatched on virtual GRN confirm; listener logs stub (full customer invoice = Phase 8/11 integration).

### Acceptance Criteria (v4.0)

1. Supplier invoice with matching PO/GRN lines reaches `fully_matched` and is payable. ✅
2. Price mismatch beyond tolerance flags `partially_matched` and blocks payment until resolved. ✅
3. Purchase return from GRN reduces stock and issues debit note with correct GL posting. 🔶 Stock + debit note + ledger ✅; GL **stub** pending Phase 11
4. Landed cost on GRN increases FIFO layer unit cost. 🔶 Allocation UI ✅; FIFO **stub** pending Phase 11
5. Drop-ship PO does not change warehouse on-hand; customer invoice generated on GRN confirm. 🔶 Virtual receive + sale link ✅; customer invoice **event stub** pending Phase 8/11
