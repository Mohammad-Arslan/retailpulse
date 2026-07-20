<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_policies') && ! Schema::hasColumn('leave_policies', 'eligibility_json')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->json('eligibility_json')->nullable()->after('negative_leave_balance_policy');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_policies') && Schema::hasColumn('leave_policies', 'eligibility_json')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->dropColumn('eligibility_json');
            });
        }
    }
};
