<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_cost_layers')) {
            return;
        }

        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('batch_no', 64)->nullable();
            $table->timestamp('received_at');
            $table->decimal('qty_received', 14, 4);
            $table->decimal('qty_remaining', 14, 4);
            $table->decimal('unit_cost', 14, 4);
            $table->string('valuation_method', 16);
            $table->decimal('landed_cost_amount', 14, 4)->default(0);
            $table->string('source_reference_type', 128);
            $table->unsignedBigInteger('source_reference_id');
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['product_variant_id', 'warehouse_id', 'status', 'received_at'], 'inventory_cost_layers_variant_warehouse_idx');
            $table->index(['source_reference_type', 'source_reference_id'], 'inventory_cost_layers_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
    }
};
