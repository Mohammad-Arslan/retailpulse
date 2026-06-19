# Phase 13 — Advanced Reporting & Analytics

**SRS Reference:** §3.14, §3.18 (report export)  
**Status:** Planned  
**Depends on:** Phase 5, Phase 8, Phase 11

---

## Objective

Operational and financial **reports** with custom report builder and export.

## Features

- Built-in reports: inventory valuation (FIFO/LIFO/WAC), cashier performance, sales by branch/product
- Dynamic report builder: dimensions (product, branch, date) + metrics (sales, profit)
- Save report definitions per user
- Export queue: Excel (Maatwebsite/Excel), PDF
- Permissions: `reports.view`, `reports.export`, `reports.builder`

## Acceptance Criteria

1. Inventory valuation matches manual calculation for sample SKUs.
2. Custom report saves and re-runs with same filters.
3. Export > 10k rows processes via queue without timeout.

---

## Phase Enhancements (SRS v4.0 — baseline)

### BI-Ready Export Schema (§3.29)
- All report exports include a machine-readable manifest file (`report_manifest.json`) alongside the CSV/XLSX, describing column names, data types, and grain (row = one what?).
- Export scheduler: admins can configure any saved report definition to run on a cron (daily/weekly/monthly) and email the result to a recipient list.

### Nightly ETL Snapshot Job
- A scheduled `AggregateDataMartsJob` runs nightly after midnight and populates `data_mart_sales` and `data_mart_inventory` from live transactional tables.
- The job is idempotent: if run twice for the same date, it upserts rather than duplicates.
- Failed runs dispatch a `DataMartEtlFailed` alert to system administrators.

### Pre-Built Data Mart Tables
- `data_mart_sales`: columns — `date`, `branch_id`, `product_variant_id`, `customer_id` (nullable), `cashier_id`, `quantity_sold`, `gross_revenue`, `discount_amount`, `net_revenue`, `cost_of_goods`, `gross_profit`, `tax_collected`.
- `data_mart_inventory`: columns — `date`, `branch_id`, `product_variant_id`, `opening_qty`, `closing_qty`, `net_movement`, `value_at_cost_fifo`.
- Both tables are indexed on `(date, branch_id)` for BI query performance.
- These tables back the enhanced Dashboard analytics added in Phase 27.

### Link to Phase 27 (BI Layer)
- This phase creates the mart schema and ETL infrastructure; Phase 27 adds the Power BI connector, Tableau template files, and the AI demand forecast stub.

---

## SRS v4.0 Enhancements (§3.14)

### AP Aging Report

- Supplier outstanding payables by age bucket (Current, 1–30, 31–60, 61–90, 90+ days).
- Payment run suggestions for overdue AP lines.

### Inventory Turnover Report

- Units sold / average on-hand per period; by product, category, or branch.

### Supplier Performance Report

- Average lead time vs contracted (`supplier_price_list_items.lead_time_days`).
- Fill rate (qty received vs ordered); return rate per supplier.
- Cross-link to Phase 10 supplier scoring stub.

### AR Aging Cross-Link

- AR aging report UI links to Phase 9 `ar_aging_snapshots` data; filterable consistently.

### Acceptance Criteria (v4.0)

1. AP aging report matches supplier ledger outstanding balances.
2. Inventory turnover calculates correctly for a known product/period.
3. Supplier performance report shows fill rate from PO vs GRN quantities.
