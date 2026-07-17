<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('overtime_policies') && ! Schema::hasColumn('overtime_policies', 'toil_expiry_months')) {
            Schema::table('overtime_policies', function (Blueprint $table): void {
                $table->unsignedSmallInteger('toil_expiry_months')->nullable()->after('public_holiday_applies');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('overtime_policies') && Schema::hasColumn('overtime_policies', 'toil_expiry_months')) {
            Schema::table('overtime_policies', function (Blueprint $table): void {
                $table->dropColumn('toil_expiry_months');
            });
        }
    }
};
