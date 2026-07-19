# ADR-016: Reporting & Business Intelligence

Status: Accepted

Date: 2026-07-19

Related: [ADR-014 Performance](./adr-014-performance.md) · [ADR-007 Integration Hub](./adr-007-integration-hub.md) · [ADR-017 AI Architecture](./adr-017-ai-architecture.md) · [Phase 13 — Reporting & Analytics](../phases/phase-13-reporting-analytics.md) · [Phase 27 — BI & Analytics](../phases/phase-27-bi-analytics.md)

---

## Why

An ERP's reporting needs split into two workloads with fundamentally different performance and freshness requirements: **operational reporting** (a cashier's shift totals, today's stock level, a specific invoice) needs to be exact and current-second, and runs against the same schema the business transacts against. **Analytical reporting** (a 90-day sales trend, gross margin by category, a demand forecast) needs to scan large historical ranges and aggregate across millions of rows — a query shape that, run directly against OLTP tables, competes with and degrades the checkout/POS paths sharing that database ([ADR-014](./adr-014-performance.md)). Treating both as "just run a report query" either makes operational reports too heavy to justify real-time use, or makes analytical reports so expensive they get rate-limited/scheduled defensively at the cost of usefulness.

## What

Operational reporting queries live OLTP data directly. Analytical/BI reporting is served from a **pre-built, nightly-ETL'd data mart** — a denormalized, aggregate-shaped copy of the data optimized for scanning, not transacting — with external BI tools (Power BI, Tableau) reading the mart through a scoped, read-only credential, never the live transactional schema.

## How

### Operational reporting (Phase 13)

Built-in reports (inventory valuation, cashier performance, sales by branch/product, AP/AR aging, inventory turnover, supplier performance) query live tables directly through the normal Repository/Service layers ([ADR-003](./adr-003-backend-architecture.md)) — these need current data and run at a scale (a shift, a day, a supplier) that ordinary indexed OLTP queries handle fine. The dynamic report builder (dimensions × metrics, saved per user) is also operational-tier: it's a flexible query composer over live tables, not a mart consumer. Exports over the row-count threshold that would timeout a synchronous request (10k+ rows, per Phase 13's acceptance criterion) process via a queued job ([ADR-014](./adr-014-performance.md)), producing CSV/PDF plus a machine-readable `report_manifest.json` describing columns, types, and grain — so a downstream consumer (a script, a future BI tool) can interpret an export without guessing at its shape.

### The data mart (Phase 13 schema, Phase 27 consumers)

`data_mart_sales`, `data_mart_inventory`, and `data_mart_ar_aging` are nightly-grain, denormalized aggregate tables — one row per `(date, branch_id, product_variant_id)` (or `customer_id` for AR aging), with a composite unique index enabling idempotent upsert. They are populated by `AggregateDataMartsJob`, a nightly scheduled ETL (01:00, configurable via `system_settings.bi.etl_time`) that reads live transactional tables (`sale_items`, `sales`, `stock_movements`, AR balances) and upserts the mart — **never the reverse**; nothing ever writes from the mart back into OLTP tables, and the mart is not authoritative for anything, it is a derived, rebuildable projection. A failed ETL run dispatches an alert to system administrators and logs to `audit_logs` rather than failing silently — a stale or partially-populated mart must be visibly flagged, not quietly served as if current.

This split exists specifically so heavy analytical queries (a dashboard's 90-day trend line, an external BI dashboard scanning a year of sales) run against a schema shaped for scanning, never touching the tables a checkout transaction is concurrently writing to.

### External BI connectivity (Power BI / Tableau)

External BI tools connect through a **dedicated, read-only database user** (`bi_reader`) scoped to `SELECT`-only on the mart tables plus a small set of dimension tables (branches, product_variants, products, categories, users, customers) — never given access to OLTP tables, and never given write privilege of any kind. Credentials are rotatable (auto-rotation every 90 days) and generated/managed through Admin → Integrations → BI Connector, consistent with [ADR-007](./adr-007-integration-hub.md)'s "outbound integrations are read-only, RetailPulse remains authoritative" principle — this ADR governs the data model the connector reads from; [ADR-007](./adr-007-integration-hub.md) governs that the connector behaves like every other outbound integration (versioned, credential-scoped, no write path back in).

### Scheduled report delivery

Any saved report definition (operational or, once Phase 27 ships, mart-backed) can be scheduled (daily/weekly/monthly) for automatic email delivery via `ScheduledReportDeliveryJob`, reusing the same export pipeline as an on-demand export — scheduled delivery is not a separate reporting mechanism, it's the existing report-run-and-export path triggered by a scheduler instead of a user click.

### Predictive analytics — explicitly scoped as a stub

The Phase 27 AI demand forecast (`AiDemandForecastJob`) is a **thin integration**, not an in-house ML system: it exports 90 days of `data_mart_sales` as JSON, POSTs to an externally configured ML API (`system_settings.bi.forecast_api_url`), and upserts the response into `demand_forecasts`. When the API URL is unconfigured, the job is a no-op — forecasting is opt-in, degrades to "feature doesn't appear" rather than erroring, and never blocks any user-facing functionality on its own success. Full predictive AI (RFM segmentation, fraud detection, natural-language report building) is explicitly deferred future scope per SRS §3.29, not partially built now — see [ADR-017](./adr-017-ai-architecture.md) for how this stub relates to RetailPulse's broader AI strategy.

## Trade-offs

- **The mart is up to 24 hours stale** (nightly ETL, not real-time) — accepted for analytical/trend use cases where same-day precision isn't the point; dashboards needing current-day operational numbers (today's sales total) read live OLTP data instead, not the mart. A feature needing both (today's number plus a 90-day trend) combines a live query and a mart query rather than forcing the mart to also be real-time.
- **Maintaining ETL adds an operational component** (a nightly job that can fail, that needs monitoring) beyond "just query the live tables" — accepted because the alternative (BI tools querying OLTP directly) risks materially worse consequences: a runaway analytical query degrading checkout latency for every branch.
- **Denormalization in the mart duplicates data already present in OLTP tables** — accepted as the point of a data mart; the duplication buys query shape optimized for aggregation, which normalized OLTP tables are not.

## Alternatives considered

- **Let Power BI/Tableau query OLTP tables directly (read replica or direct connection)** — rejected as the default: even against a read replica, ad hoc BI query patterns (arbitrary joins/aggregates a business analyst builds in Power BI's UI) are unpredictable and can be arbitrarily expensive; a purpose-built mart with known grain and pre-aggregated metrics bounds that risk and is dramatically faster for the actual dashboards being built.
- **Real-time streaming ETL (mart updated continuously rather than nightly)** — rejected for the current stage: adds significant complexity (CDC/streaming infrastructure) for a use case (trend dashboards, external BI) that does not need intraday freshness; nightly batch is simpler to build, monitor, and reason about, and matches what Phase 27 actually specifies. Revisit if a concrete same-day analytical need emerges that nightly can't serve.
- **A full data warehouse / OLAP cube from the start** — rejected as over-engineering for RetailPulse's current scale: the mart tables here already give BI tools pre-aggregated, indexed access; a full OLAP investment (Snowflake/BigQuery-style warehouse, cube processing) is future scope if/when data volume or query complexity outgrows a nightly-ETL'd relational mart.

## Future direction

As tenant count and data volume grow (post-Phase-28), the nightly ETL window and mart table volume are expected to need partitioning/archival strategy, and the mart schema is expected to grow additional fact tables (e.g. HR/payroll analytics, procurement analytics) following the same grain-and-upsert pattern established here — new fact tables are additive to this architecture, not a reason to redesign it. Predictive analytics beyond the current stub (see [ADR-017](./adr-017-ai-architecture.md)) is the next deliberate phase of this ADR's scope, not an incremental add-on to the stub job.

## Impact on future development

- A new analytical/dashboard feature is built against the data mart, not against live OLTP tables with a plan to "add caching later" — this is decided at design time, not discovered under a slow-query report.
- A new BI/analytics external connector follows the read-only, mart-only, credential-scoped pattern established here — no new connector gets direct OLTP access "just this once."
- The mart's idempotent, upsert-based ETL means a failed or re-run nightly job never produces duplicate or drifting analytical data — new fact tables must preserve this property.
