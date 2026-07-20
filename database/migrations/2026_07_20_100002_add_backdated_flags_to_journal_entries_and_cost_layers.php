<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('journal_entries', 'backdated_at')) {
                    $table->timestamp('backdated_at')->nullable()->after('locked_at');
                }
                if (! Schema::hasColumn('journal_entries', 'backdated_reason')) {
                    $table->string('backdated_reason')->nullable()->after('backdated_at');
                }
            });
        }

        if (Schema::hasTable('inventory_cost_layers')) {
            Schema::table('inventory_cost_layers', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_cost_layers', 'backdated_at')) {
                    $table->timestamp('backdated_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('inventory_cost_layers', 'backdated_reason')) {
                    $table->string('backdated_reason')->nullable()->after('backdated_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                if (Schema::hasColumn('journal_entries', 'backdated_reason')) {
                    $table->dropColumn('backdated_reason');
                }
                if (Schema::hasColumn('journal_entries', 'backdated_at')) {
                    $table->dropColumn('backdated_at');
                }
            });
        }

        if (Schema::hasTable('inventory_cost_layers')) {
            Schema::table('inventory_cost_layers', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_cost_layers', 'backdated_reason')) {
                    $table->dropColumn('backdated_reason');
                }
                if (Schema::hasColumn('inventory_cost_layers', 'backdated_at')) {
                    $table->dropColumn('backdated_at');
                }
            });
        }
    }
};
