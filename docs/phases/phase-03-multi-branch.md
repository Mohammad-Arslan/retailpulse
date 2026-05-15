# Phase 3 — Multi-Branch & Centralized Management

**SRS Reference:** §3.4  
**Status:** Planned  
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

## Acceptance Criteria

1. Super Admin creates multiple branches with warehouses.
2. Branch Manager sees only assigned branch data in admin (empty modules until Phase 4+).
3. Session stores active branch; user can switch if multi-branch assigned.
