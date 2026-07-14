<?php

declare(strict_types=1);

use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;

/**
 * Idempotent upsert of default Phase 12 leave types for existing installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->defaults() as $type) {
            LeaveType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'is_paid' => $type['is_paid'],
                    'affects_payroll' => $type['affects_payroll'],
                    'payroll_deduction_component_code' => $type['payroll_deduction_component_code'],
                    'status' => $type['status'],
                ],
            );
        }
    }

    public function down(): void
    {
        LeaveType::query()
            ->whereIn('code', array_column($this->defaults(), 'code'))
            ->delete();
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     is_paid: bool,
     *     affects_payroll: bool,
     *     payroll_deduction_component_code: string|null,
     *     status: string
     * }>
     */
    private function defaults(): array
    {
        return [
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
        ];
    }
};
