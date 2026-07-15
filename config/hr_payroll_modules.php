<?php

declare(strict_types=1);

/**
 * HR / Payroll / Expenses module dependency graph (Phase 12 §13).
 * Swappable for the Phase 23 registry by replacing the HrPayrollModuleGate binding only.
 */
return [
    'expenses' => ['requires' => []],
    'hr' => ['requires' => []],
    'attendance' => ['requires' => ['hr']],
    'leave' => ['requires' => ['hr']],
    'overtime' => ['requires' => ['hr', 'attendance']],
    'payroll' => ['requires' => ['hr']],
    'employee_self_service' => ['requires' => ['hr']],
    'holiday_calendar' => ['requires' => ['hr']],
];
