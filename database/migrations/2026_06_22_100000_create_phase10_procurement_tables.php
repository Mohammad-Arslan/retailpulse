<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $create = static function (string $table, callable $callback): void {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $callback);
            }
        };

        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'email')) {
                $table->string('email')->nullable()->after('slug');
                $table->string('phone', 32)->nullable()->after('email');
                $table->string('tax_registration_no', 64)->nullable()->after('phone');
                $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('tax_registration_no');
                $table->unsignedSmallInteger('credit_terms_days')->nullable()->after('payment_terms_days');
                $table->string('currency_code', 3)->default('USD')->after('credit_terms_days');
                $table->decimal('balance', 14, 2)->default(0)->after('currency_code');
                $table->text('notes')->nullable()->after('balance');
                $table->decimal('on_time_delivery_rate', 5, 2)->nullable()->after('notes');
                $table->decimal('quality_rejection_rate', 5, 2)->nullable()->after('on_time_delivery_rate');
                $table->timestamp('last_scored_at')->nullable()->after('quality_rejection_rate');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('last_scored_at');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
                $table->softDeletes();
            }
        });

        $create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('role', 64)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $create('supplier_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('label', 64)->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('state', 64)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $create('supplier_price_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('name');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $create('supplier_price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('supplier_price_lists')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('unit_price', 14, 4);
            $table->decimal('min_qty', 14, 4)->default(1);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('functional_unit_price', 14, 4)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['price_list_id', 'product_variant_id'], 'spl_item_list_variant_uq');
        });

        $create('procurement_document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'document_type']);
        });

        $create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('reference_no', 32)->unique();
            $table->string('status', 24)->default('draft');
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('functional_total', 14, 2)->default(0);
            $table->date('expected_delivery_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('drop_ship')->default(false);
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->boolean('is_historical')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });

        $create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('qty_ordered', 14, 4);
            $table->decimal('qty_received', 14, 4)->default(0);
            $table->decimal('unit_price', 14, 4);
            $table->string('price_override_reason')->nullable();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->decimal('functional_line_total', 14, 2)->default(0);
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $create('goods_receiving_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('reference_no', 32)->unique();
            $table->string('status', 24)->default('draft');
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_virtual')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });

        $create('grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('goods_receiving_notes')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->decimal('qty_received', 14, 4);
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('grn_id')->nullable()->constrained('goods_receiving_notes')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('reference_no', 32)->unique();
            $table->string('status', 24)->default('draft');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('functional_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });

        $create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('grn_item_id')->nullable()->constrained('grn_items')->nullOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('qty_invoiced', 14, 4);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->decimal('functional_line_total', 14, 2)->default(0);
            $table->timestamps();
        });

        $create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
            $table->string('reference_no', 32)->unique();
            $table->string('payment_method', 32);
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('functional_amount', 14, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->boolean('is_advance')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('entry_type', 32);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('functional_amount', 14, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supplier_id', 'branch_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        $create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('grn_id')->constrained('goods_receiving_notes')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('reference_no', 32)->unique();
            $table->string('status', 32)->default('draft');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('grn_item_id')->constrained('grn_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('qty_returned', 14, 4);
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        $create('debit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_return_id')->nullable()->constrained('purchase_returns')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
            $table->string('reference_no', 32)->unique();
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('functional_amount', 14, 2);
            $table->string('status', 24)->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $create('po_match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('grn_id')->nullable()->constrained('goods_receiving_notes')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->string('match_status', 24);
            $table->decimal('qty_variance', 14, 4)->default(0);
            $table->decimal('price_variance', 14, 4)->default(0);
            $table->text('exception_reason')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        $create('landed_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('goods_receiving_notes')->cascadeOnDelete();
            $table->string('charge_type', 32);
            $table->string('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('functional_amount', 14, 2);
            $table->string('allocation_method', 32)->default('quantity');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $create('landed_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landed_cost_entry_id')->constrained('landed_cost_entries')->cascadeOnDelete();
            $table->foreignId('grn_item_id')->constrained('grn_items')->cascadeOnDelete();
            $table->decimal('allocated_amount', 14, 4);
            $table->decimal('functional_amount', 14, 4);
            $table->timestamps();
        });

        $create('supplier_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('attachable_type')->nullable();
            $table->unsignedBigInteger('attachable_id')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['attachable_type', 'attachable_id']);
        });

        $create('supplier_performance_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('on_time_delivery_rate', 5, 2)->nullable();
            $table->decimal('quality_rejection_rate', 5, 2)->nullable();
            $table->decimal('average_lead_time_days', 8, 2)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supplier_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_performance_scores');
        Schema::dropIfExists('supplier_attachments');
        Schema::dropIfExists('landed_cost_allocations');
        Schema::dropIfExists('landed_cost_entries');
        Schema::dropIfExists('po_match_results');
        Schema::dropIfExists('debit_notes');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('supplier_ledger_entries');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoice_items');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('grn_items');
        Schema::dropIfExists('goods_receiving_notes');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('procurement_document_sequences');
        Schema::dropIfExists('supplier_price_list_items');
        Schema::dropIfExists('supplier_price_lists');
        Schema::dropIfExists('supplier_addresses');
        Schema::dropIfExists('supplier_contacts');
    }
};
