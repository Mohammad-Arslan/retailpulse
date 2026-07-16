<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_policies') && ! Schema::hasColumn('leave_policies', 'exclude_public_holidays')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->boolean('exclude_public_holidays')->default(true)->after('proration_on_join');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_policies') && Schema::hasColumn('leave_policies', 'exclude_public_holidays')) {
            Schema::table('leave_policies', function (Blueprint $table): void {
                $table->dropColumn('exclude_public_holidays');
            });
        }
    }
};
