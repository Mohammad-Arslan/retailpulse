<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_settings')) {
            return;
        }

        if (! Schema::hasColumn('financial_settings', 'negative_inventory_policy')) {
            Schema::table('financial_settings', function (Blueprint $table) {
                $table->string('negative_inventory_policy', 32)->default('strict')->after('default_inventory_valuation_method');
            });
        }

        if (Schema::hasColumn('financial_settings', 'allow_negative_inventory')) {
            DB::table('financial_settings')
                ->where('allow_negative_inventory', true)
                ->update(['negative_inventory_policy' => 'allow']);

            DB::table('financial_settings')
                ->where('allow_negative_inventory', false)
                ->update(['negative_inventory_policy' => 'strict']);

            Schema::table('financial_settings', function (Blueprint $table) {
                $table->dropColumn('allow_negative_inventory');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_settings')) {
            return;
        }

        if (! Schema::hasColumn('financial_settings', 'allow_negative_inventory')) {
            Schema::table('financial_settings', function (Blueprint $table) {
                $table->boolean('allow_negative_inventory')->default(false)->after('default_inventory_valuation_method');
            });

            DB::table('financial_settings')
                ->where('negative_inventory_policy', 'allow')
                ->update(['allow_negative_inventory' => true]);
        }

        if (Schema::hasColumn('financial_settings', 'negative_inventory_policy')) {
            Schema::table('financial_settings', function (Blueprint $table) {
                $table->dropColumn('negative_inventory_policy');
            });
        }
    }
};
