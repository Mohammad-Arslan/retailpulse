<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('tax_group_id')->nullable();
            $table->json('variant_attributes')->nullable();
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_serials')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('name');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->decimal('cost_price', 15, 4)->default(0);
            $table->decimal('sell_price', 15, 4)->default(0);
            $table->json('attributes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_default']);
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no', 64);
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->unique(['product_variant_id', 'batch_no']);
            $table->index('expiry_date');
        });

        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('child_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->timestamps();

            $table->unique(['parent_variant_id', 'child_variant_id']);
        });

        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number', 128);
            $table->string('status', 32)->default('available');
            $table->timestamps();

            $table->unique(['product_variant_id', 'serial_number']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serials');
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_batches');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
