<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('count_schedule_rules', function (Blueprint $table) {
            $table->boolean('freeze_mode')->default(false)->after('blind_count');
        });
    }

    public function down(): void
    {
        Schema::table('count_schedule_rules', function (Blueprint $table) {
            $table->dropColumn('freeze_mode');
        });
    }
};
