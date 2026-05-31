<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only drop the index if it actually exists (not present on fresh installs)
        $indexes = collect(Schema::getIndexes('pos_carts'))->pluck('name');
        if ($indexes->contains('pos_carts_cashier_slot_active_unique')) {
            Schema::table('pos_carts', function (Blueprint $table) {
                $table->dropUnique('pos_carts_cashier_slot_active_unique');
            });
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('pos_carts'))->pluck('name');
        if (! $indexes->contains('pos_carts_cashier_slot_active_unique')) {
            Schema::table('pos_carts', function (Blueprint $table) {
                $table->unique(['cashier_id', 'slot', 'status'], 'pos_carts_cashier_slot_active_unique');
            });
        }
    }
};
