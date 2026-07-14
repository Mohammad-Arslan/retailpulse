<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_categories')) {
            Schema::create('expense_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->nullOnDelete();
                $table->string('account_mapping_key', 128)->nullable();
                $table->boolean('is_group')->default(false);
                $table->boolean('requires_receipt')->default(false);
                $table->foreignId('default_tax_type_id')->nullable()->constrained('tax_types')->nullOnDelete();
                $table->string('status', 16)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expense_approval_policies')) {
            Schema::create('expense_approval_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->decimal('min_amount', 18, 4)->default(0);
                $table->string('requires', 32)->default('manager');
                $table->string('approver_role', 64)->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->unsignedInteger('priority')->default(100);
                $table->string('status', 16)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table): void {
                $table->id();
                $table->string('expense_number', 64)->unique();
                $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->nullOnDelete();
                $table->string('vendor_party_type', 64)->nullable();
                $table->unsignedBigInteger('vendor_party_id')->nullable();
                $table->char('currency_code', 3);
                $table->decimal('exchange_rate', 18, 8)->nullable();
                $table->decimal('amount', 18, 4);
                $table->foreignId('tax_type_id')->nullable()->constrained('tax_types')->nullOnDelete();
                $table->decimal('tax_amount', 18, 4)->default(0);
                $table->decimal('functional_amount', 18, 4)->nullable();
                $table->date('expense_date');
                $table->string('payment_method', 32)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 32)->default('draft');
                $table->boolean('approval_required')->default(false);
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('accounting_event_id')->nullable()->constrained('accounting_events')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['vendor_party_type', 'vendor_party_id']);
            });
        }

        if (! Schema::hasTable('expense_attachments')) {
            Schema::create('expense_attachments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
                $table->string('disk', 32);
                $table->string('path');
                $table->string('original_name');
                $table->string('mime', 128)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_attachments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_approval_policies');
        Schema::dropIfExists('expense_categories');
    }
};
