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

        $create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8)->default('$');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        $create('tax_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->decimal('rate', 8, 4)->default(0);
            $table->string('tax_direction', 16)->default('both');
            $table->string('calculation_method', 16)->default('exclusive');
            $table->foreignId('output_tax_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('input_tax_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('tax_payable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->decimal('recoverable_percentage', 5, 2)->default(100);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        $create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 64)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('sale_invoice_id')->nullable()->constrained('sale_invoices')->nullOnDelete();
            $table->date('date');
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->char('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->foreignId('tax_type_id')->nullable()->constrained('tax_types')->nullOnDelete();
            $table->string('reason');
            $table->string('status', 16)->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'date']);
        });

        $create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->foreignId('coa_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_title');
            $table->string('account_number_masked', 32)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->char('currency_code', 3)->default('USD');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        $create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('statement_date');
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('running_balance', 14, 2)->nullable();
            $table->string('import_batch_id', 64)->nullable()->index();
            $table->string('status', 16)->default('unmatched');
            $table->timestamps();
            $table->index(['bank_account_id', 'status', 'transaction_date'], 'bank_stmt_lines_acct_status_date_idx');
        });

        $create('bank_reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_line_id')->constrained('bank_statement_lines')->cascadeOnDelete();
            $table->foreignId('journal_transaction_id')->constrained('journal_transactions')->cascadeOnDelete();
            $table->decimal('matched_amount', 14, 2);
            $table->string('match_type', 32)->default('one_to_one');
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();
        });

        $create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->date('rate_date');
            $table->string('rate_type', 16)->default('spot');
            $table->decimal('rate', 12, 6);
            $table->string('source')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['currency_id', 'rate_date', 'rate_type']);
        });

        $create('branch_accounting_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->boolean('interbranch_accounting_enabled')->default(false);
            $table->foreignId('due_from_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('due_to_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        $create('intercompany_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_reference_type', 128);
            $table->unsignedBigInteger('transfer_reference_id');
            $table->foreignId('source_legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->foreignId('destination_legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->foreignId('source_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('destination_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('settlement_status', 16)->default('open');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['transfer_reference_type', 'transfer_reference_id'], 'ict_transfer_ref_type_id_idx');
        });

        if (Schema::hasTable('intercompany_transactions')) {
            $transferRefIndexExists = collect(Schema::getIndexes('intercompany_transactions'))
                ->pluck('name')
                ->contains('ict_transfer_ref_type_id_idx');

            if (! $transferRefIndexExists) {
                Schema::table('intercompany_transactions', function (Blueprint $table) {
                    $table->index(['transfer_reference_type', 'transfer_reference_id'], 'ict_transfer_ref_type_id_idx');
                });
            }
        }

        $create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->unsignedSmallInteger('default_useful_life_months')->default(60);
            $table->string('depreciation_method', 32)->default('straight_line');
            $table->foreignId('asset_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('depreciation_expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        $create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 64)->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('asset_categories')->cascadeOnDelete();
            $table->decimal('acquisition_cost', 14, 2);
            $table->date('acquisition_date');
            $table->unsignedSmallInteger('useful_life_months');
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->string('depreciation_method', 32)->default('straight_line');
            $table->date('depreciation_start_date')->nullable();
            $table->decimal('accumulated_depreciation', 14, 2)->default(0);
            $table->foreignId('asset_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('depreciation_expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->string('location')->nullable();
            $table->foreignId('custodian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->date('last_depreciation_date')->nullable();
            $table->timestamps();
        });

        $create('petty_cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->string('name');
            $table->foreignId('coa_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->string('register_mode', 32)->default('imprest');
            $table->decimal('variance_tolerance_amount', 14, 2)->default(0);
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        $create('petty_cash_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number', 64)->unique();
            $table->foreignId('petty_cash_register_id')->constrained('petty_cash_registers')->cascadeOnDelete();
            $table->string('voucher_type', 32);
            $table->date('date');
            $table->decimal('amount', 14, 2);
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('description')->nullable();
            $table->string('approval_status', 16)->default('approved');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $create('cheques', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('party_type', 128);
            $table->unsignedBigInteger('party_id');
            $table->decimal('amount', 14, 2);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->char('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->string('cheque_no', 64);
            $table->string('bank')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('related_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['party_type', 'party_id']);
            $table->index('status');
        });

        if (Schema::hasTable('journal_transactions') && ! Schema::hasColumn('journal_transactions', 'tax_type_id')) {
            Schema::table('journal_transactions', function (Blueprint $table) {
                $table->foreignId('tax_type_id')->nullable()->after('product_variant_id')->constrained('tax_types')->nullOnDelete();
            });
        }

        if (Schema::hasTable('financial_settings') && ! Schema::hasColumn('financial_settings', 'default_tax_type_id')) {
            Schema::table('financial_settings', function (Blueprint $table) {
                $table->foreignId('default_tax_type_id')->nullable()->after('journal_numbering_mode')->constrained('tax_types')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('financial_settings') && Schema::hasColumn('financial_settings', 'default_tax_type_id')) {
            Schema::table('financial_settings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('default_tax_type_id');
            });
        }

        if (Schema::hasTable('journal_transactions') && Schema::hasColumn('journal_transactions', 'tax_type_id')) {
            Schema::table('journal_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tax_type_id');
            });
        }

        Schema::dropIfExists('cheques');
        Schema::dropIfExists('petty_cash_vouchers');
        Schema::dropIfExists('petty_cash_registers');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('intercompany_transactions');
        Schema::dropIfExists('branch_accounting_profiles');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('bank_reconciliation_matches');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('tax_types');
        Schema::dropIfExists('currencies');
    }
};
