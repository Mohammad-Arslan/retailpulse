<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->enum('status', ['active', 'suspended', 'completing', 'completed', 'voided'])->default('active');
            $table->tinyInteger('slot')->unsigned();
            $table->text('notes')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['cashier_id', 'status']);
            $table->index(['cashier_id', 'slot']);
        });

        Schema::create('pos_cart_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('cart_id');
            $table->foreign('cart_id')->references('id')->on('pos_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->enum('discount_type', ['flat', 'percent'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('line_total', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('cart_id');
        });

        Schema::create('pos_pin_lockouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('failed_attempts')->unsigned()->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cart_items');
        Schema::dropIfExists('pos_carts');
        Schema::dropIfExists('pos_pin_lockouts');
    }
};
