<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('sell_price', 15, 4);
            $table->timestamps();

            $table->unique(['branch_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product_prices');
    }
};
