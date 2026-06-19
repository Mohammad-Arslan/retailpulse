# Phase 3 — Multi-Branch & Centralized Management

**SRS Reference:** §3.4  
**Status:** Complete (baseline); **warehouse CRUD follow-up** documented below — not yet implemented  
**Depends on:** Phase 1, Phase 2

---

## Objective

Model **branches** and **warehouses** as first-class entities with head-office visibility and per-branch operational settings.

## Database (key tables)

- `branches` — name, code, address, currency, timezone, operating_hours (JSON), receipt_footer, is_active, `tenant_id`
- `warehouses` — branch_id, name, code, is_default
- `branch_user` — pivot: user_id, branch_id, is_primary (branch-scoped access)
- Extend `users` context: active `branch_id` in session

## Features

- Head Office Console: list/create/edit branches
- Assign users to branches (restrict Branch Manager / Cashier visibility)
- Per-branch settings UI: currency, timezone, hours, default warehouse, receipt footer
- Middleware `SetBranchContext` — scopes queries where applicable
- Permissions: `branches.view`, `branches.create`, `branches.update`, `branches.delete`

**Current implementation (baseline):** Creating a branch also creates **one default warehouse** (name/code on the branch form). Branch edit allows selecting the default warehouse from existing warehouses for that branch only. There is **no standalone warehouse admin module yet** — see planned follow-up below.

## Acceptance Criteria

1. Super Admin creates multiple branches with warehouses.
2. Branch Manager sees only assigned branch data in admin (empty modules until Phase 4+).
3. Session stores active branch; user can switch if multi-branch assigned.

---

## Planned Follow-Up: Warehouse CRUD (§3.4 — deferred)

Multi-warehouse per branch is required by §3.6 (transfers, bins, cycle count). Implement a **dedicated, permission-gated warehouse module** — separate from branch create/edit.

### Permissions

| Permission | Description |
| :--- | :--- |
| `warehouses.view` | List warehouses (scoped to user's assigned branch(es)) |
| `warehouses.create` | Add a warehouse under a branch |
| `warehouses.update` | Edit name, code; set or clear default for branch |
| `warehouses.deactivate` | Set `is_active = false` (never hard-delete if stock or movements exist) |

**Role guidance:** Super Admin / Head Office — all branches; Branch Manager — own branch(es) only; Cashier — no warehouse admin (uses branch default in POS).

### Routes & UI

- `GET/POST admin/warehouses` — index (filter by branch), create
- `GET/PUT admin/warehouses/{id}` — edit
- `PATCH admin/warehouses/{id}/deactivate` — soft deactivate
- Admin → **Organisation → Warehouses** (or nested under branch show page with link to full list)
- Branch create: keep creating **first** default warehouse inline; additional warehouses via warehouse CRUD

### Service layer

- Extend `WarehouseRepository` — `paginateForBranch`, `update`, `deactivate`
- `WarehouseService` — validate unique `code` per branch; enforce at least one active warehouse per branch; `setDefaultForBranch` when marking default
- Audit log on create/update/deactivate

### Business rules

- `warehouses.branch_id` required; `code` unique per branch
- Cannot deactivate the **only** active warehouse for a branch
- Cannot deactivate a warehouse with `quantity_on_hand > 0` or open transfers — operator must transfer stock first
- Inactive warehouses excluded from inventory receive, transfer, and import pickers (Phase 5)
- Removing inline-only warehouse fields from branch edit is **optional** once CRUD ships; default warehouse can remain a dropdown on branch settings

### Acceptance Criteria (warehouse CRUD)

1. Branch Manager with `warehouses.create` adds a second warehouse to their branch; it appears in inventory and transfer pickers.
2. User without `warehouses.view` receives 403 on warehouse index.
3. Deactivate blocked when warehouse has on-hand stock; succeeds when empty.
4. Setting a warehouse as default clears `is_default` on siblings for that branch.
5. Super Admin sees warehouses across all branches; Branch Manager sees only assigned branches.
