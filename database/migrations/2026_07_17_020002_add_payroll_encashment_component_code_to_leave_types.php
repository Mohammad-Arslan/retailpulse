<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_types') && ! Schema::hasColumn('leave_types', 'payroll_encashment_component_code')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->string('payroll_encashment_component_code', 64)->nullable()->after('payroll_deduction_component_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_types') && Schema::hasColumn('leave_types', 'payroll_encashment_component_code')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->dropColumn('payroll_encashment_component_code');
            });
        }
    }
};
