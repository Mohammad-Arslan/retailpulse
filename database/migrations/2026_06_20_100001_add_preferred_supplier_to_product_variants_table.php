<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('preferred_supplier_id')
                ->nullable()
                ->after('reorder_point')
                ->constrained('suppliers')
                ->nullOnDelete();
            $table->json('alternate_supplier_ids')->nullable()->after('preferred_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_supplier_id');
            $table->dropColumn('alternate_supplier_ids');
        });
    }
};
