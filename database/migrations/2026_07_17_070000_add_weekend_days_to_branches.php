<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'weekend_days')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->json('weekend_days')->nullable()->after('operating_hours');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'weekend_days')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropColumn('weekend_days');
            });
        }
    }
};
