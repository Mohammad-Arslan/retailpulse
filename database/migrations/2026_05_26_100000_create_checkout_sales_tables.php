<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 32)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('ntn', 32)->nullable();
            $table->string('cnic', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('cart_id')->nullable();
            $table->foreign('cart_id')->references('id')->on('pos_carts')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', [
                'draft',
                'pending_payment',
                'partially_paid',
                'completed',
                'voided',
                'refunded',
            ])->default('draft');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->decimal('balance_due', 12, 2);
            $table->char('currency', 3)->default('PKR');
            $table->enum('tax_mode', ['inclusive', 'exclusive'])->default('exclusive');
            $table->text('notes')->nullable();
            $table->boolean('is_historical')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['cashier_id', 'created_at']);
            $table->index('cart_id');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->enum('discount_type', ['flat', 'percent'])->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->decimal('line_total', 12, 2);
            $table->decimal('tax_rate', 6, 4)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total_inc_tax', 12, 2);
            $table->timestamps();

            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customers');
    }
};
