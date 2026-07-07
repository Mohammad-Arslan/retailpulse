<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_accounting_profiles', function (Blueprint $table) {
            $table->json('accounting_enabled_modules')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('branch_accounting_profiles', function (Blueprint $table) {
            $table->dropColumn('accounting_enabled_modules');
        });
    }
};
