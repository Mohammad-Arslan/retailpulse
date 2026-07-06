# RetailPulse — Phase Gaps Register

Tracked gaps between **phase specifications** (`docs/phases/`) and the **current codebase**.  
Last reviewed: 2026-07-06.

## Severity legend

| Severity | Meaning |
| :--- | :--- |
| **Critical** | Blocks acceptance criteria or core safety; fix before calling the phase complete. |
| **High** | Important feature or spec item missing; users will notice or workflows break. |
| **Medium** | Partial implementation, polish, or prep for a later phase; should be scheduled. |
| **Low** | Stretch goals, optional items, or verification-only (e.g. Lighthouse). |

---

## Phase 1 — Super Admin, Authentication & RBAC

**Phase doc status:** Mostly complete  
**Overall gap level:** Low (few follow-ups)

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P1-01 | **Deactivate vs delete users** — `is_active` exists but `UserController::destroy` / `UserService::delete` still hard-delete users | **High** | Phase doc §14; prefer deactivate-only or explicit delete confirmation in UI. |
| P1-02 | **`users.assign-roles` not enforced server-side** — `UserPolicy::assignRoles` exists but `UserService::create` / `update` call `syncRoles()` without checking permission | **High** | UI may hide roles; API/form can still submit role changes. |
| P1-03 | **`user_permission_overrides`** — migration + model only; no service or user-edit tab | **Medium** | Marked stretch in Phase 1; SRS §3.2 user-specific grants/revokes. |
| P1-04 | Breeze scaffold tests may not match redirect-based auth routes | **Low** | Optional; not a delivery gate per project policy. |
| P1-05 | **Supplier payment policy alignment** — `abort_unless` replaced with `SupplierPaymentPolicy` + `authorize()` | — | **Resolved 2026-07-06** |
| P1-06 | **Loyalty API read authorization** — wallet/transactions/campaigns gated by `pos.access` or `loyalty.view` | — | **Resolved 2026-07-06** |
| P1-07 | **Import/export job owner-only access** — `show`/`cancel`/`download` require `user_id` match | — | **Resolved 2026-07-06** |
| P1-08 | **Inventory check-availability route auth** — moved to `web`+`auth`+`pos.access`; FormRequest checks `inventory.view` | — | **Resolved 2026-07-06** |

---

## Phase 2 — Platform Shell & Design System

**Phase doc status:** Complete  
**Overall gap level:** Minimal

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P2-01 | **Lighthouse accessibility ≥ 90** on admin dashboard not verified in repo | **Low** | Acceptance criterion in phase doc; manual/CI check only. |
| P2-02 | Breeze auth pages may still differ from full shadcn polish vs admin shell | **Low** | Functional; Phase 2 marked complete for shell consistency. |

---

## Phase 3 — Multi-Branch & Centralized Management

**Phase doc status:** Complete  
**Overall gap level:** None significant

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P3-01 | No material gaps vs acceptance criteria | — | Branches, warehouses, `SetBranchContext`, switcher, permissions, user assignment implemented. |
| P3-02 | **`warehouses.type` column + admin UI** — `WarehouseType` enum, DTOs, create/edit/index | — | **Resolved 2026-07-06** |

---

## Phase 4 — Product Information Management (PIM)

**Phase doc status:** Complete  
**Overall gap level:** Medium (two functional gaps)

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P4-01 | **`reorder_point` not editable in product/variant UI** — DB column exists on `product_variants` | **High** | Blocks low-stock alerts (Phase 5/6); operators cannot set thresholds. |
| P4-02 | **Serial capture on stock receive** — `product_serials` + serialized type exist; receive flow has no serial input | **High** | Phase 4: “serial capture on receive (Phase 5)”. |
| P4-03 | **`tax_group_id`** nullable, no tax UI | **Low** | Deferred to Phase 14 per phase doc. |

---

## Phase 5 — Inventory & Warehouse Management

**Phase doc status:** Complete  
**Overall gap level:** Medium

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P5-01 | **FEFO/FIFO picking strategy** — `allocateDeductionLines` uses branch strategy; FEFO join column ambiguity fixed | — | **Resolved 2026-07-06** (service tests added) |
| P5-02 | **Low-stock detection depends on `reorder_point`** — see P4-01 | **High** | Count/query logic exists in `DashboardService` / broadcast payload; data entry missing. |
| P5-03 | **Reserve/release on cart hold** — wired in `PosCartService` | — | **Resolved** — implemented with Phase 7 POS. |
| P5-04 | Stock availability API exists (`POST /api/v1/inventory/check-availability`) | — | **Not a gap** — acceptance criterion met. |
| P5-05 | **Stock mutation single source of truth** — `BinLocationService` / `QuarantineService` route through `InventoryService` | — | **Resolved 2026-07-06** |

---

## Phase 6 — Dashboard & Real-Time Business Intelligence

**Phase doc status:** Planned (partial implementation in codebase)  
**Overall gap level:** High (largest open surface)

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P6-01 | **Phase doc status outdated** — still “Planned”; Reverb, channels, events, activity feed partially delivered | **Low** | Update `phase-06-dashboard-realtime.md` when closing phase. |
| P6-02 | **Sales KPIs missing** — Today’s Sales, Gross Profit, ATV not on dashboard | **Medium** | Phase 8 dependency; doc allows stub/zero until sales exist. |
| P6-03 | **Pending Approvals KPI** not implemented | **Medium** | No approval workflow module yet. |
| P6-04 | **WoW / MoM revenue charts** not implemented | **Medium** | Requires Phase 8 sales data or seeded mock data. |
| P6-05 | **Permissions `dashboard.view` and `dashboard.view-profit`** not in `PermissionSeeder`; dashboard uses `admin.dashboard.view` only | **High** | Spec names differ; profit-sensitive widgets not permission-gated. |
| P6-06 | **Branch filter on all widgets** — partial only | **High** | Live feed requires active branch; super-admin ops KPIs are global; RBAC charts not branch-scoped. |
| P6-07 | **Configurable widget visibility** not implemented | **Medium** | Per-user or per-role dashboard layout not built. |
| P6-08 | **`private-admin.{userId}` channel** authorized in `routes/channels.php` but not subscribed in frontend | **Low** | Branch channel used for feed; admin channel reserved for future. |
| P6-09 | **Low-stock alert in feed &lt; 2s** — depends on Reverb running, `.env` / `VITE_REVERB_*`, and reorder points (P4-01) | **High** | Acceptance criterion; end-to-end not guaranteed without ops config + data. |
| P6-10 | **Reverb local ops** — `composer run dev` includes `reverb:start`; production deployment/WebSocket TLS not documented in phase doc | **Medium** | Infra gap for non-local environments. |

### Phase 6 — Implemented (not gaps)

- `laravel/reverb` installed; `config/broadcasting.php`, `config/reverb.php`
- Broadcasting auth: `web` + `auth` on `/broadcasting/auth`
- Channels: `admin.{userId}`, `branch.{branchId}`
- Events: `InventoryStockChanged`, `UserLoggedIn` (`ShouldBroadcastNow`)
- Echo client + `DashboardRealtimeActivity` component
- Super-admin operations snapshot on dashboard (`DashboardService::superAdminOverview`)

---

## Phase 7 — Point of Sale

**Phase doc status:** Planned (substantial implementation in codebase)  
**Overall gap level:** Medium

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P7-01 | **`pos_discount_logs` audit table** — discounts validated server-side but not logged with approval chain | **Medium** | Phase doc §discount approval. |
| P7-02 | **Manager PIN for large discounts** — API supports `approved` flag; POS UI does not collect approver PIN | **High** | `DiscountModal` passes null approver. |
| P7-03 | **`pos.override-stock` not wired** — permission seeded; no override flow on OOS warnings | **High** | Blocks override AC when stock warnings shown. |
| P7-04 | **Offline mode incomplete** — `pos-sw.js` + IndexedDB skeleton; fetch interception disabled | **Medium** | Stretch AC in phase doc. |
| P7-05 | **Customer credit WebSocket banner on POS** — `CustomerCreditLimitWarning` event exists; POS does not subscribe | **Low** | Cross-phase with P9-02. |

### Phase 7 — Implemented (not gaps)

- POS SPA (`resources/js/Pages/POS/Index.jsx`), keyboard shortcuts, F10 checkout handoff
- Cart CRUD, suspend/resume, void, stock warnings, `PosCartService` reservations
- PIN verify/set/lockout (`PosPinService`)
- Product search/catalog APIs behind `pos.access`

---

## Phase 8 — Checkout, Payments & Invoicing

**Phase doc status:** ~95% complete  
**Overall gap level:** Medium

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P8-01 | **Live payment gateway HTTP drivers** — `SalePaymentProcessor` stub/disabled only | **High** | Stripe/JazzCash/EasyPaisa not integrated. |
| P8-02 | **Layaway overdue surfacing** — `max_layaway_balance_days` setting; no overdue UI alerts | **Medium** | |
| P8-03 | **Historical sales dashboard toggle** — KPIs exclude `is_historical`; no UI to include | **Medium** | AC #6 in phase doc. |
| P8-04 | **Shift/register context (Phase 17)** — checkout has no register/shift binding | **High** | Blocks production go-live per SRS. |
| P8-05 | **Gift card tender + COGS on complete** — deferred; `SaleCompleted` triggers loyalty only | **Medium** | Cross-phase 24 / 11. |

### Phase 8 — Implemented (not gaps)

- Checkout lifecycle, split tender, layaway, tax pipeline, invoice PDF, FBR queue/block modes
- Historical sale import API (`POST /api/v1/sales/import-historical`)
- Configuration via `system_settings` groups (`tax`, `checkout`, `fbr`)

---

## Phase 9 — Customers & Loyalty

**Phase doc status:** Complete  
**Overall gap level:** Low–Medium

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P9-01 | **Customer group → price list at POS** — groups CRUD exists; auto pricing is Phase 18 | **Medium** | |
| P9-02 | **POS credit-limit WebSocket warning** — event broadcast; POS screen does not consume | **Medium** | See P7-05. |
| P9-03 | **Gift card lookup at checkout** | **Low** | Explicitly Phase 24. |
| P9-04 | **AR polish** — aging/statements exist; SMS/WhatsApp delivery unverified | **Low** | |

### Phase 9 — Implemented (not gaps)

- Customer CRUD, credit limits, wallet top-up, loyalty programs/tiers/campaigns
- `CustomerImportHandler`, loyalty earn on sale complete, redemption APIs

---

## Phase 10 — Suppliers & Procurement

**Phase doc status:** Core complete  
**Overall gap level:** Medium

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P10-01 | **GL / FIFO cost layers stubbed** — `NullProcurementPostingHook` for landed cost, returns, payments | **High** | Blocks Phase 11 integration. |
| P10-02 | **Historical PO bulk import** — `is_historical` column; no import handler | **Medium** | |
| P10-03 | **Procurement alert delivery** — DB alerts only; email/SMS deferred Phase 14 | **Medium** | |
| P10-04 | **Procurement report export queue** — in-app reports only | **Low** | |
| P10-05 | **Drop-ship customer invoice** — virtual GRN stub; no customer invoice generation | **Medium** | |

### Phase 10 — Implemented (not gaps)

- PO → approval → GRN → supplier invoice → payment workflow
- `SupplierImportHandler`, match exceptions, purchase returns, landed cost entries

---

## Phase 11 — Accounting & Finance

**Phase doc status:** Planned  
**Overall gap level:** Critical (module not started)

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P11-01 | **Double-entry GL stack** — COA, journals, posting rules | **Critical** | No `chart_of_accounts` / `journal_entries` migrations. |
| P11-02 | **Auto-post on sale complete** — `SaleCompleted` has loyalty listener only | **Critical** | |
| P11-03 | **`inventory_cost_layers` + COGS** | **High** | Spec in phase doc; not built. |
| P11-04 | **Financial statements** (Trial Balance, P&L, Balance Sheet) | **High** | |
| P11-05 | **COA / opening balance import (X-06)** | **Medium** | |

---

## Phase 12 — Expenses & HR / Payroll

**Phase doc status:** Planned  
**Overall gap level:** High (module not started)

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P12-01 | **Expense module** (entry, categories, recurring scheduler) | **High** | |
| P12-02 | **HR / payroll module** | **High** | |
| P12-03 | **POS clock-in/out via cashier PIN** | **Medium** | |
| P12-04 | **Leave/overtime/payslip (v4.0 stretch)** | **Low** | |

---

## Phase 13 — Reporting & Analytics

**Phase doc status:** Planned  
**Overall gap level:** High (platform not built)

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P13-01 | **Built-in reports** — inventory valuation, cashier performance, sales-by-branch | **High** | Domain reports exist (procurement, loyalty); not full suite. |
| P13-02 | **Dynamic report builder + saved definitions** | **High** | |
| P13-03 | **Queued Excel/PDF export (X-07)** | **Medium** | |
| P13-04 | **Data mart ETL** (`data_mart_sales`, scheduled aggregation) | **Medium** | |

---

## Phase 14 — Notifications, Returns & Tax Engine

**Phase doc status:** Planned  
**Overall gap level:** Critical (customer returns missing)

| ID | Gap | Severity | Notes |
| :--- | :--- | :---: | :--- |
| P14-01 | **Customer return/refund workflow** | **Critical** | Purchase returns exist (Phase 10); not customer returns. |
| P14-02 | **Composite tax engine** (`tax_groups`, inclusive flags) | **High** | Checkout uses flat `TaxCalculationService` only. |
| P14-03 | **Notification preferences + admin broadcast** | **High** | |
| P14-04 | **Fraud controls** (price override logs, void logs) | **Medium** | |
| P14-05 | **Alert delivery** (email/SMS for low-stock, procurement) | **Medium** | |

---

## Cross-cutting — Data import, export & onboarding (SRS §3.18)

**Status:** Framework **implemented** 2026-06+; partial gaps remain.

| ID | Gap | Severity | Target phase |
| :--- | :--- | :---: | :--- |
| X-01 | Bulk product import/export | — | **Resolved** — `ProductImportHandler` / `ProductExportHandler` + catalog entities. |
| X-02 | Opening stock import | — | **Resolved** — `InventoryImportHandler`, `inventory-adjustments`. |
| X-03 | Shared `import_export_jobs` framework | — | **Resolved** — wizard, queued jobs, `ImportExportRegistry`. |
| X-04 | Historical sales archive import | **Medium** | Phase 8 — dedicated API exists; not in generic import registry. |
| X-05 | Customer/supplier bulk import | — | **Resolved** — `CustomerImportHandler`, `SupplierImportHandler`. |
| X-06 | COA / opening balance import | **Medium** | Phase 11 |
| X-07 | Report Excel/PDF export queue | **Medium** | Phase 13 |
| X-08 | Import/export API endpoints | **Low** | Partial — admin session routes exist; Phase 15 external API TBD. |

**Onboarding critical path (new retailer):** Product import → opening stock → POS go-live (Phase 7) → optional historical sales for charts.

---

## Cross-phase dependencies

```mermaid
flowchart LR
  P4_01[P4-01 reorder_point UI] --> P5_02[P5-02 low-stock data]
  P4_01 --> P6_09[P6-09 feed alert AC]
  P8[Phase 8 Sales] --> P6_02[P6-02 sales KPIs]
  P8 --> P6_04[P6-04 revenue charts]
  P17[Phase 17 Shifts] --> P8_04[P8-04 register context]
  P10_01[P10-01 GL stub] --> P11_01[P11-01 accounting module]
  P11_01 --> X06[X-06 COA import]
```

---

## Recommended fix order

1. **P4-01** — Reorder point on variant/product form (unblocks P5-02, P6-09).  
2. **P1-02**, **P1-01** — RBAC enforcement and deactivate-only users.  
3. **P8-04** / Phase 17 — Shift/register before production checkout.  
4. **P7-02**, **P7-03** — Discount approval PIN and stock override.  
5. **P6-05**, **P6-06** — Dashboard permissions and branch-scoped widgets.  
6. **P11-01**, **P10-01** — Accounting module + procurement GL hooks.  
7. **P14-01** — Customer returns workflow.  
8. **P4-02** — Serial capture on receive.  
9. **P8-01** — Live payment gateways when required.

---

## Summary by severity

| Severity | Count (approx.) |
| :--- | :---: |
| Critical | 3 |
| High | 24 |
| Medium | 22 |
| Low | 10 |

*Counts exclude resolved rows (P1-05–P1-08, P3-02, P5-01/P5-03/P5-05, X-01–X-03/X-05) and “not a gap” / implemented subsections.*
