<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pay_components')) {
            Schema::create('pay_components', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->string('type', 32); // earning|deduction|employer_contribution|statutory|reimbursement
                $table->string('calculation_type', 32); // fixed|percentage_of|table_lookup|formula
                $table->foreignId('basis_component_id')->nullable()->constrained('pay_components')->nullOnDelete();
                $table->decimal('rate', 16, 6)->nullable();
                $table->text('formula_expression')->nullable(); // stored but never eval'd
                $table->boolean('taxable')->default(false);
                $table->string('account_mapping_key', 128)->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->index(['status', 'type', 'calculation_type']);
            });
        }

        if (! Schema::hasTable('salary_structures')) {
            Schema::create('salary_structures', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->string('status', 16)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('salary_structure_components')) {
            Schema::create('salary_structure_components', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('salary_structure_id')->constrained('salary_structures')->cascadeOnDelete();
                $table->foreignId('pay_component_id')->constrained('pay_components')->restrictOnDelete();
                $table->decimal('amount_or_rate', 16, 6)->nullable();
                $table->unsignedSmallInteger('sequence')->default(10);
                $table->timestamps();

                $table->unique(
                    ['salary_structure_id', 'pay_component_id'],
                    'salary_structure_components_structure_component_unique'
                );
                $table->index(['salary_structure_id', 'sequence']);
            });
        }

        if (! Schema::hasTable('tax_slabs')) {
            Schema::create('tax_slabs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->decimal('lower_bound', 20, 4);
                $table->decimal('upper_bound', 20, 4)->nullable();
                $table->decimal('fixed_amount', 20, 4)->default(0);
                $table->decimal('marginal_rate', 10, 6)->default(0); // percent
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->index(['legal_entity_id', 'status', 'effective_from']);
            });
        }

        if (! Schema::hasTable('statutory_schemes')) {
            Schema::create('statutory_schemes', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->foreignId('legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->string('calculation_type', 32)->default('percentage_of_wage');
                $table->decimal('employee_rate', 10, 6)->default(0); // percent
                $table->decimal('employer_rate', 10, 6)->default(0); // percent
                $table->decimal('wage_ceiling', 20, 4)->nullable();
                $table->string('account_mapping_key_employee', 128)->nullable();
                $table->string('account_mapping_key_employer', 128)->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->index(['legal_entity_id', 'status', 'effective_from']);
            });
        }

        if (! Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('payroll_number', 64)->nullable()->unique();
                $table->foreignId('legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('currency_code', 3)->default('PKR');
                $table->string('status', 16)->default('draft');
                $table->json('totals_json')->nullable();
                $table->unsignedBigInteger('accounting_event_id')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['legal_entity_id', 'status', 'period_start']);
            });
        }

        if (! Schema::hasTable('payroll_items')) {
            Schema::create('payroll_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->decimal('gross', 20, 4)->default(0);
                $table->decimal('total_deductions', 20, 4)->default(0);
                $table->decimal('total_employer_contributions', 20, 4)->default(0);
                $table->decimal('net_pay', 20, 4)->default(0);
                $table->json('ytd_json')->nullable();
                $table->json('snapshot_json')->nullable();
                $table->timestamps();

                $table->unique(['payroll_run_id', 'employee_id']);
                $table->index(['payroll_run_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('payroll_item_lines')) {
            Schema::create('payroll_item_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
                $table->foreignId('pay_component_id')->nullable()->constrained('pay_components')->nullOnDelete();
                $table->json('component_snapshot_json')->nullable();
                $table->decimal('amount', 20, 4)->default(0);
                $table->unsignedSmallInteger('sequence')->default(10);
                $table->timestamps();

                $table->index(['payroll_item_id', 'sequence']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_item_lines');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('statutory_schemes');
        Schema::dropIfExists('tax_slabs');
        Schema::dropIfExists('salary_structure_components');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('pay_components');
    }
};
