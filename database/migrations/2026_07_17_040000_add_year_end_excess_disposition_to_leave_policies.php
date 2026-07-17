<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_policies') && ! Schema::hasColumn('leave_policies', 'year_end_excess_disposition')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->string('year_end_excess_disposition', 16)->default('expire')->after('encashment_requires_approval');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_policies') && Schema::hasColumn('leave_policies', 'year_end_excess_disposition')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->dropColumn('year_end_excess_disposition');
            });
        }
    }
};
