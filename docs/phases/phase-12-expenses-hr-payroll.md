# Phase 12 — Expense Management & HR / Payroll

**SRS Reference:** §3.12, §3.13  
**Status:** Planned  
**Depends on:** Phase 11, Phase 7 (POS for attendance)

---

## Objective

**Operating expenses** with recurring schedules and **HR/payroll** with POS clock-in.

## Database (key tables)

- `expenses`, `expense_categories`, `expense_attachments` (digital vault)
- `recurring_expenses` — schedule, next_run_at, journal template
- `employees` — linked user_id, salary_structure
- `attendance_records` — clock_in, clock_out, branch_id, pos_terminal
- `payroll_runs`, `payroll_items`

## Features

- Expense entry with receipt upload (S3/local disk)
- Recurring expense scheduler (daily job → journal entry)
- Clock in/out via cashier PIN on POS
- Payroll generation from hours + salary rules → journal post
- Permissions: `expenses.*`, `hr.*`, `payroll.process`

## Acceptance Criteria

1. Recurring rent expense auto-posts on schedule.
2. Payroll run for period creates journal entry debiting salary expense.
3. Attendance ties to employee profile and branch.

---

## Phase Enhancements (SRS v4.0)

### Payroll Approval Workflow Hook (§3.30 — Phase 29)
- Payroll run generation produces a draft payroll requiring approval before posting to journals.
- When `feature_flags.hr.workflow_payroll_approval` is enabled (Phase 29), payroll approval routes through the Workflow Engine.
- Approver receives an in-app notification; approved payroll auto-posts the salary journal entry.

### Expense Category Budgets
- `expense_budgets` table: `id`, `expense_category_id`, `branch_id`, `period` (`monthly`/`quarterly`/`annual`), `amount`, `fiscal_year_id`.
- Budget vs actuals widget on the Expenses dashboard: red/amber/green indicators.
- Alert notification dispatched when a category reaches 80% and 100% of budget.

---

## SRS v4.0 Enhancements (§3.12–3.13)

### Expense Approval Workflow

- Expenses above configurable threshold require manager approval before GL posting (PIN or Phase 29 workflow hook).

### Leave Management

- **`leave_types`** — Annual, Sick, Unpaid, Maternity/Paternity, Public Holiday, Compensatory (configurable).
- **`leave_entitlements`** — `employee_id`, `leave_type_id`, `fiscal_year`, `total_days`, `used_days`, `remaining_days`.
- **`leave_requests`** — workflow: Employee → Line Manager → HR; statuses `pending`/`approved`/`rejected`/`cancelled`.
- Leave calendar for branch managers; minimum coverage warnings.
- Carry-forward policy per leave type; unpaid leave deducted in payroll.

### Overtime Engine

- **`overtime_records`** — `employee_id`, `date`, `regular_hours`, `overtime_hours`, `overtime_type`, `rate_multiplier`, `approved_by`.
- Triggers: daily > 8h, weekly > 48h, public holiday/rest day work.
- Rate multipliers: weekday 1.5×, weekend 2.0×, holiday 2.5× (configurable).
- Branch Manager approval required before payroll inclusion.

### Payslip Generation & Employee Self-Service

- Payslip PDF per `payroll_item`: gross breakdown, deductions, net pay, YTD totals.
- Email on payroll confirmation; bulk send for HR.
- Employee portal scope (full UI in Phase 26 Employee app): payslips, attendance, leave balance, leave requests.

### Acceptance Criteria (v4.0)

1. Leave request approved by manager updates entitlement `used_days`.
2. Unapproved overtime excluded from payroll run; approved overtime calculated at correct multiplier.
3. Payslip PDF totals match `payroll_items` row.
4. Expense above threshold blocked until manager approves.
5. Unpaid leave days reduce net pay in payroll run.
