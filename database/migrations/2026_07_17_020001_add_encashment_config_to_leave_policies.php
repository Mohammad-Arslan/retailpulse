<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_policies') && ! Schema::hasColumn('leave_policies', 'encashment_allowed')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->boolean('encashment_allowed')->default(false)->after('out_station_deducts_balance');
                $table->decimal('encashment_max_days', 10, 2)->nullable()->after('encashment_allowed');
                $table->boolean('encashment_requires_approval')->default(true)->after('encashment_max_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_policies') && Schema::hasColumn('leave_policies', 'encashment_allowed')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->dropColumn(['encashment_allowed', 'encashment_max_days', 'encashment_requires_approval']);
            });
        }
    }
};
