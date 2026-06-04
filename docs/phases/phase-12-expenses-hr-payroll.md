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

## Phase Enhancements (SRS v3.0)

### Payroll Approval Workflow Hook (§3.29 — Phase 29)
- Payroll run generation produces a draft payroll requiring approval before posting to journals.
- When `feature_flags.hr.workflow_payroll_approval` is enabled (Phase 29), payroll approval routes through the Workflow Engine.
- Approver receives an in-app notification; approved payroll auto-posts the salary journal entry.

### Expense Category Budgets
- `expense_budgets` table: `id`, `expense_category_id`, `branch_id`, `period` (`monthly`/`quarterly`/`annual`), `amount`, `fiscal_year_id`.
- Budget vs actuals widget on the Expenses dashboard: red/amber/green indicators.
- Alert notification dispatched when a category reaches 80% and 100% of budget.
