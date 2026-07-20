<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leave_policies', 'negative_leave_balance_policy')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->string('negative_leave_balance_policy', 32)->default('block')->after('carry_forward_expiry_months');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_policies', 'negative_leave_balance_policy')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->dropColumn('negative_leave_balance_policy');
            });
        }
    }
};
