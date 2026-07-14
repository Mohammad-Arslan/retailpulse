# RetailPulse User Manual — HR, Expenses & Payroll

**Audience:** HR managers, payroll officers, line managers, accountants, implementation consultants  
**Version:** 1.0 (July 2026)  
**Scope:** Phase 12 — operating expenses, employees, attendance, leave, overtime, payroll runs, payslips, and employee self-service

**See also:**
- [`phases/phase-12-expenses-hr-payroll.md`](phases/phase-12-expenses-hr-payroll.md) — technical specification
- [`user-manual-accounting-and-finance.md`](user-manual-accounting-and-finance.md) — how expense/payroll events post to the GL

---

## 1. Module gating

HR / Payroll features are gated **per branch** via `branch_hr_profiles.hr_enabled_modules` (mirrors Accounting Modules). Keys:

| Module | Requires |
|--------|----------|
| **Expenses** | — |
| **HR** | — |
| **Attendance** | HR |
| **Leave** | HR |
| **Overtime** | HR + Attendance |
| **Payroll** | HR |
| **Employee Self-Service** | HR |

If a module is off: nav is hidden and routes return **403**.

---

## 2. Admin navigation map

Section **HR & Payroll**:

| Item | Permission (typical) | Module |
|------|----------------------|--------|
| **Employees** | `hr.view-employees` | `hr` |
| **Expense Categories** | `expenses.manage-categories` | `expenses` |
| **Expenses** | `expenses.view` | `expenses` |
| **Recurring Expenses** | `expenses.manage-recurring` | `expenses` |
| **Attendance Sources** | `attendance.manage-sources` | `attendance` |
| **Attendance Records** | `attendance.view` | `attendance` |
| **Leave Types** | `leave.manage-types` | `leave` |
| **Leave Requests** | `leave.view` | `leave` |
| **Overtime Policies** | `overtime.manage-policies` | `overtime` |
| **Overtime Records** | `overtime.view` | `overtime` |
| **Pay Components** | `payroll.manage-components` | `payroll` |
| **Tax Slabs** | `payroll.manage-tax-slabs` | `payroll` |
| **Statutory Schemes** | `payroll.manage-statutory` | `payroll` |
| **Payroll Runs** | `payroll.view` | `payroll` |
| **My Payslips** | `selfservice.view-own` | `employee_self_service` |

---

## 3. Expenses

### 3.1 Categories

Admin → **Expense Categories**. Set **Requires Receipt** per category. Optional **Account Mapping Key** overrides `expense_default` at post time (payload key, not a hard-coded account ID).

### 3.2 Enter and approve

1. Admin → **Expenses** → **New Expense**.
2. Fill category, entity, branch, amount, currency, tax, payment method.
3. Attach receipt if the category requires it.
4. **Submit** / **Approve**.
5. If an **Expense Approval Policy** matches (amount / branch / category), the expense stays **Pending Approval** until approved.

On approval the system publishes `expense.posted` (idempotent). There is no direct journal write from expense services.

### 3.3 Recurring

Admin → **Recurring Expenses**. Schedules generate one occurrence per `period_key` (unique with schedule). Command: `expenses:process-recurring` (scheduled daily). Publishes `expense.recurring_due`.

---

## 4. Employees & attendance

- **Employees:** Master records with legal entity, primary branch, optional salary structure, optional linked user.
- **Attendance:** Source-agnostic. Drivers: **POS PIN**, **Manual**; stubs for biometric / mobile / import.
- New drivers = implement `AttendanceSourceProvider` + a config row — payroll code is untouched.

---

## 5. Leave & overtime

- **Leave types** seeded (Annual / Sick / Unpaid) — editable. Unpaid / over-balance leave resolves to a configured pay-component code for payroll (not a hard-coded salary cut).
- **Overtime:** Thresholds and multipliers come only from **Overtime Policies**. Unapproved records are excluded from payroll jobs.

---

## 6. Payroll

### 6.1 Configuration

| Screen | Purpose |
|--------|---------|
| **Pay Components** | fixed / percentage_of / table_lookup. **Formula** is rejected: “Formula Components Are Not Supported Yet”. |
| **Tax Slabs** | Progressive income tax by legal entity / effective date. |
| **Statutory Schemes** | EOBI, GPSSA, etc. — rates + mapping keys only (no new calculation code per scheme). |

### 6.2 Run lifecycle

```text
Draft → (Calculate) → Pending Approval → Approved → Posted → Reversed
```

| Step | GL? |
|------|-----|
| Calculate | No — builds draft items + frozen `snapshot_json` |
| Approve | **No** — state only (workflow or approval-limit fallback both land here) |
| Post | **Yes** — publishes `payroll.posted` once |
| Reverse | Linked Phase 11 reversal journal via `payroll.reversed` |

### 6.3 Payslips & self-service

- Generate PDF per payroll item; totals must match item lines.
- Bulk email uses queued mail.
- **My Payslips** for users linked to an employee (`employee_self_service`). Full mobile UI is Phase 26.

---

## 7. Troubleshooting

| What if… | Check |
|----------|--------|
| Payroll nav missing | Branch HR profile includes `payroll` (+ `hr`); user has `payroll.view` |
| Expense won't approve | Receipt required? Policy threshold? |
| Payroll approved but no journal | Click **Post** — approve never posts |
| Duplicate journal on re-post | Idempotency: same `payroll.posted:PayrollRun:{id}` returns the existing event |
| Formula component | Not supported in this phase — use fixed / percentage_of / table_lookup |

---

## Document history

| Version | Date | Notes |
|---------|------|-------|
| 1.0 | July 2026 | Initial Phase 12 HR / Expenses / Payroll user manual |
