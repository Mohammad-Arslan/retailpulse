<?php

declare(strict_types=1);

use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;

/**
 * Idempotent upsert of Phase 12 §7 account_mappings keys for existing installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $accounts = ChartOfAccount::query()
            ->whereIn('code', ['1300', '2100', '2210', '5200', '5300'])
            ->pluck('id', 'code');

        $mappings = [
            ['mapping_key' => 'expense_default', 'account_code' => '5300'],
            ['mapping_key' => 'payroll_expense', 'account_code' => '5200'],
            ['mapping_key' => 'overtime_expense', 'account_code' => '5200'],
            ['mapping_key' => 'employer_contribution_expense', 'account_code' => '5200'],
            ['mapping_key' => 'net_salary_payable', 'account_code' => '2100'],
            ['mapping_key' => 'tax_withheld_payable', 'account_code' => '2210'],
            ['mapping_key' => 'employee_advance_receivable', 'account_code' => '1300'],
            ['mapping_key' => 'reimbursement_payable', 'account_code' => '2100'],
        ];

        foreach ($mappings as $mapping) {
            $accountId = $accounts->get($mapping['account_code']);

            if ($accountId === null) {
                continue;
            }

            AccountMapping::query()->firstOrCreate(
                [
                    'mapping_key' => $mapping['mapping_key'],
                    'payment_method' => null,
                ],
                [
                    'account_id' => $accountId,
                    'status' => 'active',
                    'priority' => 100,
                ],
            );
        }
    }

    public function down(): void
    {
        AccountMapping::query()
            ->whereIn('mapping_key', [
                'expense_default',
                'payroll_expense',
                'overtime_expense',
                'employer_contribution_expense',
                'net_salary_payable',
                'tax_withheld_payable',
                'employee_advance_receivable',
                'reimbursement_payable',
            ])
            ->delete();
    }
};
