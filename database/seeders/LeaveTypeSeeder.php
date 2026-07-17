<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

final class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'code' => 'ANNUAL',
                'name' => 'Annual Leave',
                'is_paid' => true,
                'affects_payroll' => false,
                'payroll_deduction_component_code' => null,
                'status' => 'active',
            ],
            [
                'code' => 'SICK',
                'name' => 'Sick Leave',
                'is_paid' => true,
                'affects_payroll' => false,
                'payroll_deduction_component_code' => null,
                'status' => 'active',
            ],
            [
                'code' => 'UNPAID',
                'name' => 'Unpaid Leave',
                'is_paid' => false,
                'affects_payroll' => true,
                'payroll_deduction_component_code' => 'UNPAID_LEAVE',
                'status' => 'active',
            ],
            [
                'code' => 'TOIL',
                'name' => 'Time Off In Lieu',
                'is_paid' => true,
                'affects_payroll' => false,
                'payroll_deduction_component_code' => null,
                'allow_leave_claim' => true,
                'allow_cash_claim' => true,
                'status' => 'active',
            ],
        ];

        foreach ($defaults as $type) {
            LeaveType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'is_paid' => $type['is_paid'],
                    'affects_payroll' => $type['affects_payroll'],
                    'payroll_deduction_component_code' => $type['payroll_deduction_component_code'],
                    'allow_leave_claim' => $type['allow_leave_claim'] ?? true,
                    'allow_cash_claim' => $type['allow_cash_claim'] ?? false,
                    'status' => $type['status'],
                ],
            );
        }
    }
}
