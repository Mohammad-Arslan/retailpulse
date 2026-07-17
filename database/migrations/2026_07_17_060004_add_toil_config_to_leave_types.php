<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_types') && ! Schema::hasColumn('leave_types', 'allow_leave_claim')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->boolean('allow_leave_claim')->default(true)->after('payroll_encashment_component_code');
                $table->boolean('allow_cash_claim')->default(false)->after('allow_leave_claim');
                $table->string('payroll_toil_payout_component_code', 64)->nullable()->after('allow_cash_claim');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_types') && Schema::hasColumn('leave_types', 'allow_leave_claim')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->dropColumn(['allow_leave_claim', 'allow_cash_claim', 'payroll_toil_payout_component_code']);
            });
        }
    }
};
