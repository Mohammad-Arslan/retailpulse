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
