<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            $table->dropUnique('pos_carts_cashier_slot_active_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pos_carts', function (Blueprint $table) {
            $table->unique(['cashier_id', 'slot', 'status'], 'pos_carts_cashier_slot_active_unique');
        });
    }
};
