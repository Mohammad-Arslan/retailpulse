<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('overtime_records') && ! Schema::hasColumn('overtime_records', 'compensation_choice')) {
            Schema::table('overtime_records', function (Blueprint $table): void {
                $table->string('compensation_choice', 16)->nullable()->after('resolved_multiplier');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('overtime_records') && Schema::hasColumn('overtime_records', 'compensation_choice')) {
            Schema::table('overtime_records', function (Blueprint $table): void {
                $table->dropColumn('compensation_choice');
            });
        }
    }
};
