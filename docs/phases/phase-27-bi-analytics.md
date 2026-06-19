# Phase 27 — Business Intelligence & Analytics

**SRS Reference:** §3.29
**Status:** Planned
**Depends on:** Phase 13 (Reporting — report builder and export infrastructure), Phase 9 (AR aging snapshots), Phase 23 (Module Config Engine — bi module gate)
**Feeds into:** Phase 28 (SaaS — BI access gated per plan)

---

## Objective
Elevate the system's analytics from operational reports to a BI-ready platform: populate pre-built data marts via nightly ETL, expose a dedicated read-only database user for Power BI / Tableau, add enhanced dashboard analytics with trend lines, and implement an AI demand forecast stub that calls an external ML API.

---

## 1. Data Mart Tables

### data_mart_sales (nightly grain)
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| date | date | |
| branch_id | bigint FK | |
| product_variant_id | bigint FK | |
| category_id | bigint FK nullable | Denormalised for query speed |
| cashier_id | bigint FK nullable | |
| customer_id | bigint FK nullable | |
| quantity_sold | decimal(12,4) | |
| gross_revenue | decimal(14,2) | Before discounts |
| discount_amount | decimal(14,2) | |
| net_revenue | decimal(14,2) | After discounts, before tax |
| cost_of_goods | decimal(14,2) | FIFO cost * qty |
| gross_profit | decimal(14,2) | net_revenue − cost_of_goods |
| tax_collected | decimal(14,2) | |

### data_mart_inventory (nightly grain)
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| date | date | |
| branch_id | bigint FK | |
| product_variant_id | bigint FK | |
| opening_qty | decimal(12,4) | |
| closing_qty | decimal(12,4) | |
| net_movement | decimal(12,4) | closing − opening |
| value_at_cost_fifo | decimal(14,2) | |

Both tables have a composite unique index on `(date, branch_id, product_variant_id)` for upsert idempotency.

### data_mart_ar_aging (nightly grain) — SRS v4.0

| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| date | date | Snapshot date |
| branch_id | bigint FK | |
| customer_id | bigint FK | |
| current | decimal(14,2) | Not yet due |
| bucket_30 | decimal(14,2) | 1–30 days |
| bucket_60 | decimal(14,2) | 31–60 days |
| bucket_90 | decimal(14,2) | 61–90 days |
| bucket_over_90 | decimal(14,2) | 90+ days |
| total_outstanding | decimal(14,2) | |

Populated from live AR balances (Phase 9); unique index on `(date, branch_id, customer_id)`.

### demand_forecasts (AI stub output)
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| branch_id | bigint FK | |
| product_variant_id | bigint FK | |
| forecast_date | date | Day being forecast |
| predicted_qty | decimal(12,4) | |
| confidence | decimal(5,4) | 0.00–1.00 |
| model_version | varchar(50) | Version of the ML model |
| generated_at | timestamp | |

---

## 2. Nightly ETL Job

`AggregateDataMartsJob` — runs at 01:00 every night (configurable via `system_settings.bi.etl_time`).

```
For each branch:
    For each (date = yesterday):
        Aggregate sale_items + sales → data_mart_sales (UPSERT)
        Aggregate stock_movements → data_mart_inventory (UPSERT)
        Aggregate ar_aging_snapshots → data_mart_ar_aging (UPSERT)

On failure:
    dispatch DataMartEtlFailedNotification to system administrators
    log to audit_logs
```

The job is idempotent: running it twice for the same date upserts, never duplicates.

---

## 3. Power BI / Tableau Connector

**Read-only DB user:**
- A `bi_reader` MySQL user with `SELECT` privilege only on `data_mart_sales`, `data_mart_inventory`, `data_mart_ar_aging`, `demand_forecasts`, and the dimension tables (`branches`, `product_variants`, `products`, `categories`, `users`, `customers`).
- Password auto-rotated every 90 days; current credentials stored encrypted in `system_settings`.

**Connection String Generator:**
- Admin → Integrations → BI Connector page.
- "Generate Credentials" button creates/rotates the `bi_reader` password and displays a copyable connection string.
- Power BI `.pbix` template file downloadable; Tableau `.twb` template file downloadable.
- Templates ship with pre-built dashboards: Sales Overview, Inventory Valuation, Staff Performance, Customer Cohorts.

---

## 4. Scheduled Report Delivery

- Any saved report definition (Phase 13) can be scheduled: daily / weekly / monthly.
- `ScheduledReportDeliveryJob`: runs the report query, generates CSV/PDF via the Phase 13 export pipeline, and emails to a configured recipient list.
- `report_schedules` table: `id`, `report_definition_id`, `frequency` (`daily`/`weekly`/`monthly`), `time_of_day`, `recipients` JSON, `last_run_at`, `is_active`.

---

## 5. AI Demand Forecast Stub

`AiDemandForecastJob` — weekly scheduled job.

1. Exports the last 90 days of `data_mart_sales` as a JSON payload.
2. POSTs to `system_settings.bi.forecast_api_url` with Bearer token from `system_settings.bi.forecast_api_key`.
3. On success: parses the response (array of `{branch_id, variant_id, forecast_date, predicted_qty, confidence}`) and upserts `demand_forecasts`.
4. On failure: logs error; does not block any user-facing functionality.

When the API URL is not configured, the job is a no-op (stub mode).

---

## 6. Enhanced Dashboard Analytics

New widgets added to the Dashboard (Phase 6 base):
- **Sales Trend (7/30/90 days):** Line chart from `data_mart_sales`, filterable by branch. Uses mart data for speed (not live OLTP).
- **Gross Profit Margin:** Sparkline trend from mart, current vs previous period.
- **Top 10 Products by Profit:** Bar chart from mart.
- **Demand Forecast Widget:** Shows predicted qty for top 5 low-stock variants in the next 7 days (from `demand_forecasts`). Only shown when forecast data exists.

---

## 7. Admin UI

- **Analytics → Sales Dashboard:** Enhanced KPI page with mart-powered charts.
- **Analytics → Inventory Dashboard:** Stock value trend, slow movers, days-of-stock-remaining.
- **Analytics → Demand Forecasts:** Table of upcoming forecasts with confidence scores; link to reorder suggestion.
- **Settings → BI Connector:** Generate/rotate credentials; download templates; configure forecast API.
- **Settings → Report Schedules:** Create/manage scheduled report deliveries.

---

## 8. Services & Classes

- `AggregateDataMartsJob` — nightly ETL.
- `AiDemandForecastJob` — weekly forecast refresh.
- `ScheduledReportDeliveryJob` — scheduled report email delivery.
- `BiConnectorService` — credential rotation, connection string generation.
- `DemandForecastService` — read forecasts, surface reorder suggestions.
- `DataMartEtlFailedNotification` — alert on ETL failure.

---

## SRS v4.0 Enhancements (§3.29)

### AR Aging Data Mart

- Nightly ETL populates `data_mart_ar_aging` from Phase 9 `ar_aging_snapshots` or live AR calculation.
- Powers AR trend charts on dashboard and external BI templates.

### AI & Predictive Analytics — Future Scope

Full AI modules (demand forecasting beyond stub, RFM, fraud detection, NL report builder) are **deferred** per SRS §3.29 future scope. This phase delivers only the **demand forecast stub** via configurable external ML API.

### Acceptance Criteria (v4.0)

1. `data_mart_ar_aging` row counts match customer count with outstanding balance for snapshot date.
2. BI reader user cannot `INSERT`/`UPDATE`/`DELETE` on mart tables.
3. Forecast stub no-ops cleanly when API URL unset.
