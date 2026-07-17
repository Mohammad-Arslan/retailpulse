<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_policies') && ! Schema::hasColumn('leave_policies', 'exclude_weekends')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->boolean('exclude_weekends')->default(true)->after('exclude_public_holidays');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_policies') && Schema::hasColumn('leave_policies', 'exclude_weekends')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->dropColumn('exclude_weekends');
            });
        }
    }
};
