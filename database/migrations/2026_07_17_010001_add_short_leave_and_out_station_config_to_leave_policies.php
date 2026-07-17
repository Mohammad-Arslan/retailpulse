<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_policies') && ! Schema::hasColumn('leave_policies', 'short_leave_max_hours')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->decimal('short_leave_max_hours', 6, 2)->nullable()->after('exclude_public_holidays');
                $table->unsignedSmallInteger('short_leave_max_requests_per_month')->nullable()->after('short_leave_max_hours');
                $table->boolean('out_station_deducts_balance')->default(false)->after('short_leave_max_requests_per_month');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_policies') && Schema::hasColumn('leave_policies', 'short_leave_max_hours')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->dropColumn(['short_leave_max_hours', 'short_leave_max_requests_per_month', 'out_station_deducts_balance']);
            });
        }
    }
};
