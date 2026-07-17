# RetailPulse User Manual — HR, Expenses & Payroll

**Audience:** HR managers, payroll officers, line managers, accountants, implementation consultants  
**Version:** 1.16 (July 2026)  
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
| **Employment Types** | `hr.manage-settings` | `hr` |
| **HR Settings** | `hr.manage-settings` | `hr` |
| **HR Modules** | `hr.manage-settings` | `hr` |
| **Org Chart** | `hr.view-employees` | `hr` |
| **Approval Delegations** | `hr.manage-org` | `hr` |
| **Holiday Calendars** | `holiday.manage` | `holiday_calendar` |
| **Expense Categories** | `expenses.manage-categories` | `expenses` |
| **Expenses** | `expenses.view` | `expenses` |
| **Recurring Expenses** | `expenses.manage-recurring` | `expenses` |
| **Attendance Sources** | `attendance.manage-sources` | `attendance` |
| **Attendance Records** | `attendance.view` | `attendance` |
| **Leave Types** | `leave.manage-types` | `leave` |
| **Leave Policies** | `leave.manage-policies` | `leave` |
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

1. Admin → **Departments** — hierarchical units per legal entity (parent cycle checks; cannot deactivate while active employees are assigned). **Code** is previewed on create (`DEPT-00001` style), assigned uniquely on save, and read-only. Same auto-code pattern on **Designations** (`DESIG-#####`), **Grades** (`GRADE-#####`), and **Holiday Calendars** (`HOL-#####`). Optional default cost centre. **Import / Export** toolbar on the list (`departments.import` / `departments.export`).
2. Admin → **Designations** — job titles; optional default grade. Import/export via shared wizard (`designations.import` / `designations.export`).
3. Admin → **Grades** — pay grades / bands with optional effective dating. **Currency** is chosen from active Accounting **Currencies** (not free text). Import/export via shared wizard (`grades.import` / `grades.export`).
4. Admin → **Employment Types** — configurable employment categories (entity-scoped or global; slug codes such as `full_time`). **Import / Export** toolbar on the list (requires `hr.manage-settings`). Used on employee forms and validation.
5. Admin → **HR Settings** — per legal entity: default holiday calendar, **Employee Code Sequence Key** (document sequence name; default `employee` → codes like `EMP-00001`), **Leave Fiscal Year Mode** (Calendar Year / Fiscal Year / Hire Anniversary), cost-centre requirement toggle, **Work Hours Per Day** (default 8 — used to convert short-leave hours into days), and **Weekend Days** (default Saturday/Sunday — used when a leave policy has Exclude Weekends enabled). Create calendars first under **Holiday Calendars**, then pick one as the entity default.

Permission: `hr.manage-org` (masters), `hr.manage-settings` (employment types + entity settings).

### 4.2 Employees

1. Admin → **Employees** → **New Employee**. Creation is a **4-step wizard**: Basic Information → Service Info → Company Info → Bank Accounts (optional). **Employee Code** is previewed on create (`EMP-#####` style) and assigned on save. Use **Continue** / **Back**; **Create Employee** on the last step saves and opens **Edit**.
2. **Edit / View** uses a left **Profile Sections** rail (full page width) for: Basic, Service, Company, Dependents (including gender and national ID), Working Shifts (preferences only), National Holidays, Attendance, Attachments, Medical, Bank Accounts. **Save Changes** stays sticky at the bottom of the form.
3. **Import / Export** on the Employees list uses the shared Import/Export wizard (permissions: `employees.import` / `employees.export`). A second toolbar imports **Reporting Hierarchy** only (`reporting-hierarchy.import` / `reporting-hierarchy.export`) — columns: Employee Code, Manager Employee Code, optional Effective From.
4. **Effective-dated org changes:** On Edit → Company Info, set **Effective From** when changing department, designation, grade, or primary branch. Future-dated rows apply automatically; **Assignment History** on the employee profile shows org and manager timelines.
5. **Attachments:** Image uploads via the shared **Image** model (JPG/PNG/WebP). Use **Add More** to queue several upload groups in one save — each group has its own document type (Photo, CNIC, ID Copy, Other). Multi-select with preview inside a group; **CNIC** shows dedicated **Front** and **Back** slots. Marked removals apply on **Save Changes**.
6. **Bank accounts:** When any accounts exist, exactly one must be marked **Primary**. Currency options come from active Accounting **Currencies**.
7. Master records link legal entity, primary branch, optional department / designation / grade / reporting manager / salary structure.
8. Changing reporting manager writes **manager history**; changing department/designation/grade writes **assignment history** (with optional effective dating).
9. Reporting manager assignment rejects self-report and cycles.
10. Secondary **branch assignments** (effective dates) are edited under Company Info on Edit (not during the create wizard).

Permission: `hr.view-employees` / `hr.manage-employees` (plus import/export permissions for spreadsheet flows).

### 4.2.1 Org master import columns

| Entity | Match Key | Notable Columns |
|--------|-----------|-----------------|
| Departments | Code | Legal Entity, Parent Code, Cost Centre Code, Status |
| Designations | Code | Legal Entity, Grade Code, Status |
| Grades | Code | Legal Entity, Min/Mid/Max, Currency, Effective Dates, Status |
| Employment Types | Code (+ optional Legal Entity) | Name, Status (blank Legal Entity = global) |
| Holiday Calendars | Calendar Code + Holiday Date | Flat rows: calendar metadata + one date per row |
| Reporting Hierarchy | Employee Code | Manager Employee Code, Effective From (optional) |

See [`generic-import-export.md`](generic-import-export.md) for wizard mechanics.

### 4.2.2 Employee import columns

| Column | Required | Notes |
|--------|----------|-------|
| Employee Code | Update/Upsert | Optional on Create (auto-issued). Match key for Update/Upsert. |
| First Name / Last Name | Yes | |
| Hire Date | Yes | |
| Legal Entity | Yes | Exact legal name or tax registration no. |
| Primary Branch Code | Yes | Branch `code` |
| Employment Type | No | Code from **Employment Types** master (entity-scoped or global); defaults to first active type |
| Status | No | `active`, `inactive`, `terminated` (default `active`) |
| Department / Designation / Grade Code | No | Org master codes |
| Manager Employee Code | No | Must already exist; import managers first |
| Cost Centre / Salary Structure Code | No | |
| Email, Phone, Gender, DOB, Marital Status, Nationality, National ID, dates, payment method, title, names | No | See template |

Modes: **Create**, **Update**, **Upsert**. Export respects list filters (search, status, branch).

See also: [`generic-import-export.md`](generic-import-export.md).

### 4.3 Reporting hierarchy

1. Admin → **Org Chart** — collapsible tree from active employees; filter by legal entity and optional root employee.
2. Admin → **Approval Delegations** — temporary redirect of approvals from one employee to another (scope: All, Leave, Expense, Overtime) with effective dates. Search by from/to employee name or code.
3. Leave requests populate `approval_chain_json` with the resolved direct manager (respecting active delegations).

### 4.4 Holiday calendars

1. Enable module key **`holiday_calendar`** on the branch via Admin → **HR Modules** (requires **HR**). After enable, **Holiday Calendars** appears in the sidebar.
2. Admin → **Holiday Calendars** — create calendar (**Code** auto-previewed as `HOL-#####`, assigned on save). On the calendar detail page, **Edit Calendar** updates name, legal entity, branch, and status (code remains read-only). Add dates (unique per calendar) with optional **Paid Holiday** and **Recurring Pattern** (month/day for annual generation via `php artisan hr:generate-recurring-holidays {year}`). Assign to legal entity / branch / employee with effective from/to and status. Deleting a date or assignment asks for confirmation. Import may still supply an explicit calendar code per row.
3. **Import / Export** on the calendar list uses flat rows (calendar metadata + holiday date columns). Resolution order for a given employee/date: **employee → branch → legal entity** (higher priority wins).
4. **Leave** day counts exclude public holidays when the active leave policy has **Exclude Public Holidays** enabled (Admin → **Leave Policies**).
5. **Overtime** uses the `public_holiday` day-type multiplier when the active overtime policy has **Public Holiday Applies** and the date is a public holiday on the employee's resolved calendar.

### 4.5 Attendance

- Source-agnostic. Drivers: **POS PIN**, **Manual**; stubs for biometric / mobile / import.
- New drivers = implement `AttendanceSourceProvider` + a config row — payroll code is untouched.
- **Historical Import** — Admin → **Attendance Records** has an Import/Export toolbar (same generic wizard as Employees/Departments/etc.) for bulk-loading past attendance data on customer onboarding. Columns: Employee Code, Branch Code (optional), Clock In, Clock Out (optional), Worked Minutes (optional, computed from Clock In/Out if left blank). Imported rows are flagged **Historical** in the list and are written directly to the record without going through the live clock-in/out pipeline (no double-counting against currently open records). Permissions: `attendance.import`, `attendance.export`.

---

## 5. Leave & overtime

- **Leave types** seeded (Annual / Sick / Unpaid) — editable via modal create/edit.
- **Leave policies** — modal create/edit for accrual method (`fixed_annual` / `monthly_accrual` / `per_worked_hours`), rate, max balance, carry-forward, proration on join, effective dates, and legal-entity scope. Quick-toggle **Exclude Public Holidays** remains on the list for day counting; **Exclude Weekends** (default on) works the same way, using the weekend days configured per legal entity (Admin → **HR Settings** → **Weekend Days**, default Saturday/Sunday — not every customer's weekend falls on the same days). Also configurable: **Short Leave Max Hours** (per-request cap, blank = unlimited), **Short Leave Max Requests / Month** (blank = unlimited), and **Out Station Deducts Balance** (default off). Permission: `leave.manage-policies`.
- **Leave request duration types** — **Full Day** (default, unchanged behaviour), **Half Day** (single date, requires a Morning/Afternoon session, always 0.5 days), **Short Leave** (single date, requires start/end time, counts as `hours ÷ Work Hours Per Day` — the latter is set per legal entity under Admin → **HR Settings** → `work_hours_per_day`, default 8), and **Out Station** (full-day equivalent for attendance/approval, but only deducts from the leave balance if the applicable policy's **Out Station Deducts Balance** is enabled — the decision is captured on the request at submission time, so later policy changes never retroactively affect an already-submitted request).
- New leave requests resolve the direct manager into the approval chain (delegations apply). Pending requests show **Approve** and **Reject** (`leave.approve`).
- **Leave Encashment** (Admin → **Leave Encashments**) — employees/HR convert unused leave balance into a payroll payout. Requires the leave type's policy to have **Allow Encashment** enabled; optional **Encashment Max Days** per request; **Encashment Requires Approval** (default on) — when off, requests auto-approve and deduct immediately. Encashed days are tracked in a separate `encashed_days` balance (never mixed with days actually taken), and an approved encashment is picked up by the next payroll run as an earning line via the leave type's **Encashment Component Code** (Admin → **Leave Types**), using the same daily-rate formula (`basis component ÷ payroll.leave_days_in_month`) already used for unpaid-leave deductions. Permissions: `leave.request-encashment`, `leave.approve-encashment`.
- **Leave Year-End Processing** — runs automatically once a day (`leave:process-year-end`, scheduled 01:30) and is safe to re-run (idempotent per legal entity/period). Trigger and boundary depend on the entity's **Leave Fiscal Year Mode** (Admin → **HR Settings**): **Calendar Year** (processes every Jan 1 for the year that just ended), **Fiscal Year** (processes once each accounting `FiscalYear` row for that entity has ended), or **Hire Anniversary** (processes each employee individually on their hire-date anniversary). For every entitlement: days up to the policy's **Carry Forward Limit** carry over; anything above it is either expired ("use it or lose it", the default) or automatically encashed, per the policy's **Year-End Excess Disposition**. Days held by a still-**pending** leave request are never expired or encashed — they always carry forward untouched, so an approval processed after year-end can never come up short. Results are visible under Admin → **Leave Year-End Runs** (`leave.view`).
- **Overtime:** Thresholds and multipliers come only from **Overtime Policies**. Create/edit policies in a modal (legal entity / branch scope, daily & weekly thresholds, rest-day and public-holiday flags, priority, and multipliers for weekday / weekend / rest_day / public_holiday). Public holidays on the employee calendar use the `public_holiday` multiplier when enabled. Pending overtime records show **Approve** and **Reject** (`overtime.approve`). Unapproved (and rejected) records are excluded from payroll jobs.

---

## 6. Payroll

### 6.1 Configuration

| Screen | Purpose |
|--------|---------|
| **Pay Components** | fixed / percentage_of / table_lookup. **Formula** is rejected: “Formula Components Are Not Supported Yet”. Modal create/edit/delete. |
| **Tax Slabs** | Progressive income tax by legal entity / effective date. Modal create/edit/delete (`payroll.manage-tax-slabs`). |
| **Statutory Schemes** | EOBI, GPSSA, etc. — rates + mapping keys only (no new calculation code per scheme). Modal create/edit (`payroll.manage-statutory`). |

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
- **My Payslips** for users linked to an employee (`employee_self_service`). If the signed-in user has no linked employee, they are redirected with an error toast instead of a blank 403 page. Full mobile UI is Phase 26.

---

## 7. Troubleshooting

| What if… | Check |
|----------|--------|
| My Payslips shows error toast | User account must be linked to an employee (`employees.user_id`); Super Admin without a link cannot view ESS payslips |
| Payroll nav missing | Branch HR profile includes `payroll` (+ `hr`); user has `payroll.view` |
| Expense won't approve | Receipt required? Policy threshold? |
| Payroll approved but no journal | Click **Post** — approve never posts |
| Duplicate journal on re-post | Idempotency: same `payroll.posted:PayrollRun:{id}` returns the existing event |
| Formula component | Not supported in this phase — use fixed / percentage_of / table_lookup |

---

## Document history

| Version | Date | Notes |
|---------|------|-------|
| 1.21 | July 2026 | Wave 2: Attendance historical import via the generic import/export wizard (`attendance` entity), `is_historical` flag on attendance records |
| 1.20 | July 2026 | Wave 2: Leave year-end carry-forward/expire/encash job (`leave:process-year-end`, daily scheduled) — supports Calendar Year / Fiscal Year / Hire Anniversary modes; pending-request-safe; Leave Year-End Runs admin view |
| 1.19 | July 2026 | Wave 2: Weekend exclusion in leave day counting — `exclude_weekends` per policy, configurable `weekend_days` per legal entity (default Sat/Sun) |
| 1.18 | July 2026 | Wave 2: Leave encashment — policy config, request/approve/reject/cancel lifecycle, separate `encashed_days` balance, payroll payout wiring |
| 1.17 | July 2026 | Wave 2: Leave duration types (Full Day / Half Day / Short Leave / Out Station), short-leave hour/monthly caps, Out Station non-deduction flag, `work_hours_per_day` HR setting |
| 1.16 | July 2026 | Wave 2 UI: Pay Components + Leave Types CRUD; brand buttons / empty states polish on leave & attendance |
| 1.15 | July 2026 | Leave Policies modal create/edit; Leave Request and Overtime Record Reject actions |
| 1.14 | July 2026 | Overtime Policies, Tax Slabs, and Statutory Schemes admin modal CRUD |
| 1.13 | July 2026 | Missing linked employee on My Payslips redirects with error toast instead of full-page 403 |
| 1.12 | July 2026 | Wave 1 UI gap closure: holiday edit + paid/assignment fields, employee code preview + dependent fields, employment-type import/export, master placeholders, delegation search |
| 1.11 | July 2026 | Holiday Calendar create uses auto-generated `HOL-#####` codes (same pattern as Departments) |
| 1.10 | July 2026 | Wave 1 UI consistency: HR Settings fiscal-year Select + sequence-key hints, holiday delete confirm, list empty states / row actions aligned |
| 1.9 | July 2026 | Wave 1 closure: HR settings, employment types, org imports, org chart, delegations, leave holiday day count, OT public-holiday multiplier, recurring holidays command |
| 1.8 | July 2026 | Employee import/export via shared Import/Export wizard |
| 1.7 | July 2026 | Employee attachments: Add More for multiple document types in one save |
| 1.6 | July 2026 | Employee attachments: multi-image + CNIC front/back previews via Image model |
| 1.5 | July 2026 | Employee create = 4-step wizard; edit/show = full-width section sidebar + sticky Save |
| 1.4 | July 2026 | Employee create/edit/show: 10-tab profile form (dependents, medical, banks, attachments, holiday prefs) |
| 1.3 | July 2026 | Grade currency uses active Currencies dropdown |
| 1.2 | July 2026 | Org master codes (Department / Designation / Grade) auto-previewed on create and read-only |
| 1.1 | July 2026 | Wave 1: Departments, Designations, Grades, Holiday Calendars, employee org/manager fields |
| 1.0 | July 2026 | Initial Phase 12 HR / Expenses / Payroll user manual |
