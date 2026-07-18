<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_entitlements') && ! Schema::hasColumn('leave_entitlements', 'accrual_last_run_on')) {
            Schema::table('leave_entitlements', function (Blueprint $table): void {
                $table->date('accrual_last_run_on')->nullable()->after('carried_forward_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_entitlements') && Schema::hasColumn('leave_entitlements', 'accrual_last_run_on')) {
            Schema::table('leave_entitlements', function (Blueprint $table): void {
                $table->dropColumn('accrual_last_run_on');
            });
        }
    }
};
