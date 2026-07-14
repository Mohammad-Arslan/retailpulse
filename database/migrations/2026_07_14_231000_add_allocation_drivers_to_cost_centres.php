<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_centres', function (Blueprint $table) {
            $table->unsignedInteger('headcount')->nullable()->after('status');
            $table->decimal('floor_area', 14, 4)->nullable()->after('headcount');
        });
    }

    public function down(): void
    {
        Schema::table('cost_centres', function (Blueprint $table) {
            $table->dropColumn(['headcount', 'floor_area']);
        });
    }
};
