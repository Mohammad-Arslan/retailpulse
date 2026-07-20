<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_entitlements') && ! Schema::hasColumn('leave_entitlements', 'granted_manually')) {
            Schema::table('leave_entitlements', function (Blueprint $table): void {
                $table->boolean('granted_manually')->default(false)->after('carried_forward_expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_entitlements') && Schema::hasColumn('leave_entitlements', 'granted_manually')) {
            Schema::table('leave_entitlements', function (Blueprint $table): void {
                $table->dropColumn('granted_manually');
            });
        }
    }
};
