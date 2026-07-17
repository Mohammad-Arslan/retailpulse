<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_shift_preferences')) {
            Schema::table('employee_shift_preferences', function (Blueprint $table): void {
                if (! Schema::hasColumn('employee_shift_preferences', 'weekend_days_enabled')) {
                    $table->boolean('weekend_days_enabled')->default(false)->after('rest_days');
                }
                if (! Schema::hasColumn('employee_shift_preferences', 'weekend_days')) {
                    $table->json('weekend_days')->nullable()->after('weekend_days_enabled');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_shift_preferences')) {
            Schema::table('employee_shift_preferences', function (Blueprint $table): void {
                if (Schema::hasColumn('employee_shift_preferences', 'weekend_days')) {
                    $table->dropColumn('weekend_days');
                }
                if (Schema::hasColumn('employee_shift_preferences', 'weekend_days_enabled')) {
                    $table->dropColumn('weekend_days_enabled');
                }
            });
        }
    }
};
