<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('holiday_dates')) {
            Schema::table('holiday_dates', function (Blueprint $table): void {
                if (! Schema::hasColumn('holiday_dates', 'is_recurring')) {
                    $table->boolean('is_recurring')->default(false)->after('is_paid');
                }
                if (! Schema::hasColumn('holiday_dates', 'recurrence_month')) {
                    $table->unsignedTinyInteger('recurrence_month')->nullable()->after('is_recurring');
                }
                if (! Schema::hasColumn('holiday_dates', 'recurrence_day')) {
                    $table->unsignedTinyInteger('recurrence_day')->nullable()->after('recurrence_month');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('holiday_dates')) {
            Schema::table('holiday_dates', function (Blueprint $table): void {
                $columns = array_filter(
                    ['is_recurring', 'recurrence_month', 'recurrence_day'],
                    fn (string $col) => Schema::hasColumn('holiday_dates', $col),
                );
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
