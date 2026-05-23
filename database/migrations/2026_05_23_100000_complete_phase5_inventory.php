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
        Schema::table('branches', function (Blueprint $table) {
            $table->dateTime('cutover_date')->nullable()->after('is_active');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_default');
        });

        Schema::table('import_export_jobs', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('entity_type')->constrained()->nullOnDelete();
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->unsignedInteger('qty_requested')->default(0)->after('batch_id');
            $table->unsignedInteger('qty_received')->default(0)->after('qty_requested');
        });

        DB::table('stock_transfer_items')->update([
            'qty_requested' => DB::raw('quantity'),
        ]);

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'released_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['warehouse_id', 'product_variant_id']);
        });

        DB::table('stock_movements')
            ->where('reason', 'return')
            ->update(['reason' => 'sale_return']);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->after('batch_id');
        });

        DB::table('stock_transfer_items')->update([
            'quantity' => DB::raw('qty_requested'),
        ]);

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable(false)->change();
            $table->dropColumn(['qty_requested', 'qty_received']);
        });

        Schema::table('import_export_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('cutover_date');
        });

        DB::table('stock_movements')
            ->where('reason', 'sale_return')
            ->update(['reason' => 'return']);
    }
};
