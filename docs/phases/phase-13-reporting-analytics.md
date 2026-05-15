# Phase 13 — Advanced Reporting & Analytics

**SRS Reference:** §3.14  
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
