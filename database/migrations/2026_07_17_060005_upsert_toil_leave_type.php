<?php

declare(strict_types=1);

use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;

/**
 * Idempotent upsert of the TOIL leave type for existing installs, mirroring
 * 2026_07_15_140001_upsert_phase12_leave_types.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        LeaveType::query()->updateOrCreate(
            ['code' => 'TOIL'],
            [
                'name' => 'Time Off In Lieu',
                'is_paid' => true,
                'affects_payroll' => false,
                'payroll_deduction_component_code' => null,
                'payroll_encashment_component_code' => null,
                'allow_leave_claim' => true,
                'allow_cash_claim' => true,
                'payroll_toil_payout_component_code' => null,
                'status' => 'active',
            ],
        );
    }

    public function down(): void
    {
        LeaveType::query()->where('code', 'TOIL')->delete();
    }
};
