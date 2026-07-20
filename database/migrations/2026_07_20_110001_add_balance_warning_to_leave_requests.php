<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leave_requests', 'balance_warning')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->boolean('balance_warning')->default(false)->after('deduct_from_balance');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_requests', 'balance_warning')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->dropColumn('balance_warning');
            });
        }
    }
};
