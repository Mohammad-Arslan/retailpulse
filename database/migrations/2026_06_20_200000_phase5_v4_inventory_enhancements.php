<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('bin_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zone', 32)->nullable();
            $table->string('aisle', 16)->nullable();
            $table->string('shelf', 16)->nullable();
            $table->string('bin_code', 64);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('capacity_limit')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'bin_code']);
            $table->index(['warehouse_id', 'is_active']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->foreignId('bin_location_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('bin_locations')
                ->nullOnDelete();
            $table->unsignedInteger('quantity_in_quarantine')
                ->default(0)
                ->after('quantity_reserved');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inventories_warehouse_variant_batch_unique');
            $table->unique(
                ['warehouse_id', 'product_variant_id', 'batch_id', 'bin_location_id'],
                'inventories_wh_variant_batch_bin_unique',
            );
        });

        Schema::create('variant_branch_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('reorder_point')->nullable();
            $table->unsignedInteger('safety_stock_qty')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_variant_id']);
        });

        Schema::create('count_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 32)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('status', 24)->default('draft');
            $table->boolean('blind_count')->default(false);
            $table->boolean('freeze_mode')->default(false);
            $table->decimal('variance_threshold_pct', 8, 2)->nullable();
            $table->decimal('variance_threshold_value', 15, 4)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('count_session_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('count_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bin_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_no')->nullable();
            $table->unsignedInteger('system_qty')->default(0);
            $table->unsignedInteger('counted_qty')->nullable();
            $table->integer('variance_qty')->nullable();
            $table->decimal('variance_value', 15, 4)->nullable();
            $table->string('adjustment_reason')->nullable();
            $table->timestamps();

            $table->index('count_session_id');
        });

        Schema::create('count_schedule_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('frequency', 16);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->boolean('blind_count')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('count_schedule_rules');
        Schema::dropIfExists('count_session_lines');
        Schema::dropIfExists('count_sessions');
        Schema::dropIfExists('variant_branch_settings');

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inventories_wh_variant_batch_bin_unique');
            $table->dropConstrainedForeignId('bin_location_id');
            $table->dropColumn('quantity_in_quarantine');
            $table->unique(
                ['warehouse_id', 'product_variant_id', 'batch_id'],
                'inventories_warehouse_variant_batch_unique',
            );
        });

        Schema::dropIfExists('bin_locations');
        Schema::dropIfExists('warehouse_zones');
    }
};
