<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('overtime_multipliers') && ! Schema::hasColumn('overtime_multipliers', 'compensation_type')) {
            Schema::table('overtime_multipliers', function (Blueprint $table): void {
                $table->string('compensation_type', 16)->default('cash')->after('multiplier');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('overtime_multipliers') && Schema::hasColumn('overtime_multipliers', 'compensation_type')) {
            Schema::table('overtime_multipliers', function (Blueprint $table): void {
                $table->dropColumn('compensation_type');
            });
        }
    }
};
