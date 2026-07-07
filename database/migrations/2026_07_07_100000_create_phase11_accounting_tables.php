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

        $create('organization_entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('legal_name');
            $table->string('tax_registration_no', 64)->nullable();
            $table->char('functional_currency_code', 3)->default('USD');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        $create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 16);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->unsignedTinyInteger('account_level')->default(1);
            $table->boolean('is_group')->default(false);
            $table->boolean('is_postable')->default(true);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->char('currency_code', 3)->nullable();
            $table->string('status', 16)->default('active');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        $create('financial_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->char('functional_currency_code', 3)->default('USD');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->foreignId('retained_earnings_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('current_year_earnings_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('opening_balance_equity_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('suspense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('rounding_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('fx_gain_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('fx_loss_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('default_inventory_valuation_method', 16)->default('fifo');
            $table->boolean('allow_negative_inventory')->default(false);
            $table->boolean('allow_manual_journal_posting')->default(true);
            $table->decimal('manual_journal_approval_limit', 14, 2)->nullable();
            $table->string('backdated_posting_policy', 32)->default('warn');
            $table->boolean('backdated_entry_approval_required')->default(false);
            $table->boolean('fiscal_year_close_approval_required')->default(true);
            $table->string('period_lock_mode', 32)->default('fiscal_year');
            $table->string('journal_numbering_mode', 32)->default('branch_fiscal');
            $table->date('accounting_cutover_date')->nullable();
            $table->timestamps();
        });

        $create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'start_date', 'end_date']);
        });

        $create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('mapping_key', 64);
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('payment_method', 32)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamps();
            $table->index(['mapping_key', 'status', 'priority']);
        });

        $create('posting_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('name');
            $table->string('event_type', 64);
            $table->string('entity_type', 64)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->char('currency_code', 3)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['event_type', 'status', 'priority']);
        });

        $create('posting_rule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posting_rule_set_id')->constrained('posting_rule_sets')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('entry_side', 8);
            $table->string('account_resolution_type', 64);
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('account_mapping_key', 64)->nullable();
            $table->string('amount_source', 64);
            $table->string('narration_template')->nullable();
            $table->boolean('required')->default(true);
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        $create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number', 64);
            $table->date('journal_date');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->string('source_module', 64)->nullable();
            $table->string('source_event', 64)->nullable();
            $table->string('source_reference_type', 128)->nullable();
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->string('source_number', 64)->nullable();
            $table->string('status', 32)->default('draft');
            $table->boolean('is_system_generated')->default(false);
            $table->boolean('is_opening_balance')->default(false);
            $table->boolean('is_closing_entry')->default(false);
            $table->foreignId('reversal_of_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['journal_date', 'status']);
            $table->index(['source_reference_type', 'source_reference_id']);
            $table->unique(['journal_number', 'branch_id']);
        });

        $create('journal_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_sequence')->default(1);
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('functional_currency_amount', 14, 2)->default(0);
            $table->decimal('transaction_currency_amount', 14, 2)->nullable();
            $table->char('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('party_type', 128)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('reference_type', 128)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'journal_entry_id']);
        });

        $create('accounting_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 64);
            $table->string('source_type', 128);
            $table->unsignedBigInteger('source_id');
            $table->string('idempotency_key', 191)->unique();
            $table->string('processing_status', 32)->default('pending');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['event_type', 'source_type', 'source_id']);
            $table->index('processing_status');
        });

        $create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 64);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->string('prefix', 32)->default('JV');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('reset_frequency', 16)->default('fiscal_year');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['document_type', 'branch_id', 'legal_entity_id', 'fiscal_year_id'], 'document_sequences_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('accounting_events');
        Schema::dropIfExists('journal_transactions');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('posting_rule_lines');
        Schema::dropIfExists('posting_rule_sets');
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('financial_settings');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('organization_entities');
    }
};
