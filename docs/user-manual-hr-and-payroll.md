# RetailPulse User Manual — HR, Expenses & Payroll

**Audience:** HR managers, payroll officers, line managers, accountants, implementation consultants  
**Version:** 1.8 (July 2026)  
**Scope:** Phase 12 — operating expenses, employees, attendance, leave, overtime, payroll runs, payslips, and employee self-service

**See also:**
- [`phases/phase-12/README.md`](phases/phase-12/README.md) — Enterprise HRMS modular SRS
- [`user-manual-accounting-and-finance.md`](user-manual-accounting-and-finance.md) — how expense/payroll events post to the GL

---

## 1. Module gating

HR / Payroll features are gated **per branch** via `branch_hr_profiles.hr_enabled_modules` (mirrors Accounting Modules). Keys:

| Module | Requires |
|--------|----------|
| **Expenses** | — |
| **HR** | — |
| **Holiday Calendar** | HR |
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
| **Departments** | `hr.manage-org` | `hr` |
| **Designations** | `hr.manage-org` | `hr` |
| **Grades** | `hr.manage-org` | `hr` |
| **Holiday Calendars** | `holiday.manage` | `holiday_calendar` |
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

## 4. Employees, organization & attendance

### 4.1 Organization masters

1. Admin → **Departments** — hierarchical units per legal entity (parent cycle checks; cannot deactivate while active employees are assigned). **Code** is previewed on create (`DEPT-00001` style), assigned uniquely on save, and read-only. Same auto-code pattern on **Designations** (`DESIG-#####`) and **Grades** (`GRADE-#####`). Optional default cost centre.
2. Admin → **Designations** — job titles; optional default grade.
3. Admin → **Grades** — pay grades / bands with optional effective dating. **Currency** is chosen from active Accounting **Currencies** (not free text).

Permission: `hr.manage-org`.

### 4.2 Employees

1. Admin → **Employees** → **New Employee**. Creation is a **4-step wizard**: Basic Information → Service Info → Company Info → Bank Accounts (optional). Use **Continue** / **Back**; **Create Employee** on the last step saves and opens **Edit**.
2. **Edit / View** uses a left **Profile Sections** rail (full page width) for: Basic, Service, Company, Dependents, Working Shifts (preferences only), National Holidays, Attendance, Attachments, Medical, Bank Accounts. **Save Changes** stays sticky at the bottom of the form.
3. **Import / Export** on the Employees list uses the shared Import/Export wizard (permissions: `employees.import` / `employees.export`). Download the template from the wizard. Match key is **Employee Code**. Lookups use **Legal Entity** (name or tax registration), **Primary Branch Code**, and optional department / designation / grade / manager / cost centre / salary structure **codes**. Import managers before their reports. **National ID** is accepted on import (stored encrypted) and left blank on export.
4. **Attachments:** Image uploads via the shared **Image** model (JPG/PNG/WebP). Use **Add More** to queue several upload groups in one save — each group has its own document type (Photo, CNIC, ID Copy, Other). Multi-select with preview inside a group; **CNIC** shows dedicated **Front** and **Back** slots. Marked removals apply on **Save Changes**.
5. **Bank accounts:** When any accounts exist, exactly one must be marked **Primary**. Currency options come from active Accounting **Currencies**.
6. Master records link legal entity, primary branch, optional department / designation / grade / reporting manager / salary structure.
7. Changing reporting manager writes **manager history**; changing department/designation/grade writes **assignment history**.
8. Reporting manager assignment rejects self-report and cycles.
9. Secondary **branch assignments** (effective dates) are edited under Company Info on Edit (not during the create wizard).

Permission: `hr.view-employees` / `hr.manage-employees` (plus `employees.import` / `employees.export` for spreadsheet flows).

### 4.2.1 Employee import columns

| Column | Required | Notes |
|--------|----------|-------|
| Employee Code | Update/Upsert | Optional on Create (auto-issued). Match key for Update/Upsert. |
| First Name / Last Name | Yes | |
| Hire Date | Yes | |
| Legal Entity | Yes | Exact legal name or tax registration no. |
| Primary Branch Code | Yes | Branch `code` |
| Employment Type | No | `full_time`, `part_time`, `contract`, `hourly` (default `full_time`) |
| Status | No | `active`, `inactive`, `terminated` (default `active`) |
| Department / Designation / Grade Code | No | Org master codes |
| Manager Employee Code | No | Must already exist; import managers first |
| Cost Centre / Salary Structure Code | No | |
| Email, Phone, Gender, DOB, Marital Status, Nationality, National ID, dates, payment method, title, names | No | See template |

Modes: **Create**, **Update**, **Upsert**. Export respects list filters (search, status, branch).

See also: [`generic-import-export.md`](generic-import-export.md).

### 4.3 Holiday calendars

1. Enable module key **`holiday_calendar`** on the branch HR profile (requires **HR**).
2. Admin → **Holiday Calendars** — create calendar, add dates (unique per calendar), assign to legal entity / branch / employee with effective dates.
3. Resolution order for a given employee/date: **employee → branch → legal entity** (higher priority wins). Leave/OT day-counting will consume this in a later wave; `HolidayResolver` is available now.

### 4.4 Attendance

- Source-agnostic. Drivers: **POS PIN**, **Manual**; stubs for biometric / mobile / import.
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
| 1.8 | July 2026 | Employee import/export via shared Import/Export wizard |
| 1.7 | July 2026 | Employee attachments: Add More for multiple document types in one save |
| 1.6 | July 2026 | Employee attachments: multi-image + CNIC front/back previews via Image model |
| 1.5 | July 2026 | Employee create = 4-step wizard; edit/show = full-width section sidebar + sticky Save |
| 1.4 | July 2026 | Employee create/edit/show: 10-tab profile form (dependents, medical, banks, attachments, holiday prefs) |
| 1.3 | July 2026 | Grade currency uses active Currencies dropdown |
| 1.2 | July 2026 | Org master codes (Department / Designation / Grade) auto-previewed on create and read-only |
| 1.1 | July 2026 | Wave 1: Departments, Designations, Grades, Holiday Calendars, employee org/manager fields |
| 1.0 | July 2026 | Initial Phase 12 HR / Expenses / Payroll user manual |
