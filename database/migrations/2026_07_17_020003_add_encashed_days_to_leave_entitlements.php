<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_entitlements') && ! Schema::hasColumn('leave_entitlements', 'encashed_days')) {
            Schema::table('leave_entitlements', function (Blueprint $table): void {
                $table->decimal('encashed_days', 10, 2)->default(0)->after('used_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_entitlements') && Schema::hasColumn('leave_entitlements', 'encashed_days')) {
            Schema::table('leave_entitlements', function (Blueprint $table): void {
                $table->dropColumn('encashed_days');
            });
        }
    }
};
