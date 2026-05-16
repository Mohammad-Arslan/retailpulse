# Phase 6 — Dashboard & Real-Time Business Intelligence

**SRS Reference:** §3.3, §4.1  
**Status:** Complete (sales KPIs stubbed until Phase 8)  
**Depends on:** Phase 5 (stock alerts), Phase 8 partial (sales KPIs — can stub with zero until sales exist)

---

## Objective

Operational **dashboard** with KPI widgets, comparative charts, and **Laravel Reverb** live activity feed.

## Infrastructure

- Install `laravel/reverb`, configure WebSocket server
- Channels: `private-admin.{userId}`, `private-branch.{branchId}`
- Broadcast events: placeholder until sales module; wire `InventoryStockChanged`, `UserLoggedIn`

## Features

- KPI cards: Today's Sales, Gross Profit, ATV, Low Stock Alerts, Pending Approvals (configurable visibility)
- Activity feed component subscribing to Reverb
- Charts (Recharts/Tremor): WoW / MoM revenue — requires sales data from Phase 8; use seed/mock until then
- Branch filter on all widgets
- Permissions: `dashboard.view`, `dashboard.view-profit`

## Acceptance Criteria

1. Low-stock alert appears in feed within 2s of inventory dropping below reorder level.
2. Dashboard respects branch context and permission-gated widgets.
3. Reverb channels authenticated via Sanctum/session.
