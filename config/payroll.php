<?php

declare(strict_types=1);

return [
    /**
     * Divisor used when converting a monthly basis component into a daily leave
     * deduction rate. Editable via config — never hardcoded in the engine.
     */
    'leave_days_in_month' => (int) env('PAYROLL_LEAVE_DAYS_IN_MONTH', 30),

    /** Fallback pay-component code used as leave deduction basis when none set. */
    'default_basis_component_code' => env('PAYROLL_DEFAULT_BASIS_COMPONENT', 'BASIC'),

    /** Default code for overtime pay component looked up on the salary structure. */
    'overtime_component_code' => env('PAYROLL_OVERTIME_COMPONENT', 'OVERTIME_EXPENSE'),

    'payslips_disk' => env('PAYSLIPS_DISK', 'local'),
];
