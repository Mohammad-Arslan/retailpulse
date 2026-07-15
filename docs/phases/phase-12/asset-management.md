# Phase 12 — Employee Asset Management

**Gate / registry key:** `employee_assets`  
**Wave:** 4  
**Depends on:** `hr`  
**Status (module roll-up):** Planned  
**Follows:** [architecture.md](./architecture.md)

Operational issuance of company assets to employees (laptops, phones, uniforms). Fixed-asset accounting depreciation remains Phase 11 when an asset is capitalized — this module publishes events / links; it does not bypass Phase 11.

---

## 1. Objective

Track asset catalogue, custody assignment, return, and loss/write-off with optional accounting events.

---

## 2. Actors & permissions

| Permission | Use |
| :--- | :--- |
| `employee_assets.view` / `manage` / `issue` / `return` | Custody lifecycle |

---

## 3. Functional requirements

| ID | Status | Statement |
| :--- | :--- | :--- |
| P12-AST-FR-001 | Planned | Asset categories and items with serial/tag, status (available/assigned/retired). |
| P12-AST-FR-002 | Planned | Issue to employee with condition notes and due return date. |
| P12-AST-FR-003 | Planned | Return records condition; damaged may create recovery deduction (config). |
| P12-AST-FR-004 | Planned | Offboarding checklist can require open assets returned. |
| P12-AST-FR-005 | Planned | Optional link to Phase 11 fixed asset id. |
| P12-AST-FR-006 | Planned | Events: `employee_asset.issued`, `.returned`, `.written_off`. |
| P12-AST-FR-007 | Planned | Historical custody import. |

---

## 4. Domain model

```text
employee_asset_categories
- id, code, name, requires_serial, status

employee_assets
- id, category_id, tag_code, serial_number nullable,
  fixed_asset_id nullable, status, timestamps

employee_asset_issues
- id, employee_asset_id, employee_id, issued_at, due_return_at nullable,
  condition_out, status, accounting_event_id nullable

employee_asset_returns
- id, issue_id, returned_at, condition_in, recovery_amount nullable
```

---

## 5. Services & interfaces

```text
EmployeeAssetService
```

---

## 6. Domain events

```text
employee_asset.issued
employee_asset.returned
employee_asset.written_off
```

Mapping key baseline: `employee_asset_clearing`.

---

## 7. Configurability surface

* Categories, recovery policies — config.

---

## 8. Historical migration inputs

* Asset list + current custodian.

---

## 9. Reports / ESS touchpoints

* Custody register; ESS own assets.

---

## 10. Acceptance criteria

| ID | Status | Criterion |
| :--- | :--- | :--- |
| P12-AST-AC-001 | Planned | Cannot issue asset with status ≠ available. |
| P12-AST-AC-002 | Planned | Offboarding clearance blocked if open issues and policy requires return. |
| P12-AST-AC-003 | Planned | No direct journal write from asset service. |

---

## 11. Out of scope / deferred hooks

* Full fixed-asset register/depreciation — Phase 11.
