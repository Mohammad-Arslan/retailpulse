<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_settings')) {
            Schema::table('financial_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('financial_settings', 'zero_cost_inventory_policy')) {
                    $table->string('zero_cost_inventory_policy', 32)->default('warn')->after('backdated_entry_approval_required');
                }
            });
        }

        if (Schema::hasTable('inventory_cost_layers')) {
            Schema::table('inventory_cost_layers', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_cost_layers', 'is_zero_cost')) {
                    $table->boolean('is_zero_cost')->default(false)->after('backdated_reason');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('financial_settings')) {
            Schema::table('financial_settings', function (Blueprint $table) {
                if (Schema::hasColumn('financial_settings', 'zero_cost_inventory_policy')) {
                    $table->dropColumn('zero_cost_inventory_policy');
                }
            });
        }

        if (Schema::hasTable('inventory_cost_layers')) {
            Schema::table('inventory_cost_layers', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_cost_layers', 'is_zero_cost')) {
                    $table->dropColumn('is_zero_cost');
                }
            });
        }
    }
};
