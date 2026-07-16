<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_assignment_history') && ! Schema::hasColumn('employee_assignment_history', 'effective_to')) {
            Schema::table('employee_assignment_history', function (Blueprint $table): void {
                $table->date('effective_to')->nullable()->after('effective_from');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_assignment_history', 'effective_to')) {
            Schema::table('employee_assignment_history', function (Blueprint $table): void {
                $table->dropColumn('effective_to');
            });
        }
    }
};
