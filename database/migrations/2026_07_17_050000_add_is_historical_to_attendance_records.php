<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_records') && ! Schema::hasColumn('attendance_records', 'is_historical')) {
            Schema::table('attendance_records', function (Blueprint $table): void {
                $table->boolean('is_historical')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_records') && Schema::hasColumn('attendance_records', 'is_historical')) {
            Schema::table('attendance_records', function (Blueprint $table): void {
                $table->dropColumn('is_historical');
            });
        }
    }
};
