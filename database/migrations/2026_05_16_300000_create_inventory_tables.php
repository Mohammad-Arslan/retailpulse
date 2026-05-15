<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->timestamps();

            $table->unique(
                ['warehouse_id', 'product_variant_id', 'batch_id'],
                'inventories_warehouse_variant_batch_unique',
            );
            $table->index(['warehouse_id', 'product_variant_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('reason', 32);
            $table->integer('qty_delta');
            $table->unsignedInteger('quantity_on_hand_after');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['warehouse_id', 'product_variant_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('reason');
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 32)->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['from_warehouse_id', 'to_warehouse_id']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index('stock_transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventories');
    }
};
