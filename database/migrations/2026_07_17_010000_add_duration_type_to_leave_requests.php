<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_requests') && ! Schema::hasColumn('leave_requests', 'duration_type')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->string('duration_type', 16)->default('full_day')->after('leave_type_id');
                $table->string('session', 16)->nullable()->after('duration_type');
                $table->time('start_time')->nullable()->after('session');
                $table->time('end_time')->nullable()->after('start_time');
                $table->boolean('deduct_from_balance')->default(true)->after('days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_requests') && Schema::hasColumn('leave_requests', 'duration_type')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->dropColumn(['duration_type', 'session', 'start_time', 'end_time', 'deduct_from_balance']);
            });
        }
    }
};
