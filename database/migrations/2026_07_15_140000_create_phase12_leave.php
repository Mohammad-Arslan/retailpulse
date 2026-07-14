<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 32)->unique();
                $table->string('name');
                $table->boolean('is_paid')->default(true);
                $table->boolean('affects_payroll')->default(false);
                $table->string('payroll_deduction_component_code', 64)->nullable();
                $table->string('status', 16)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('leave_policies')) {
            Schema::create('leave_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->string('accrual_method', 32);
                $table->decimal('accrual_rate', 10, 4)->default(0);
                $table->decimal('max_balance', 10, 2)->nullable();
                $table->decimal('carry_forward_limit', 10, 2)->nullable();
                $table->unsignedSmallInteger('carry_forward_expiry_months')->nullable();
                $table->boolean('proration_on_join')->default(false);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->index(['leave_type_id', 'legal_entity_id', 'status']);
            });
        }

        if (! Schema::hasTable('leave_entitlements')) {
            Schema::create('leave_entitlements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
                $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
                $table->decimal('accrued_days', 10, 2)->default(0);
                $table->decimal('used_days', 10, 2)->default(0);
                $table->decimal('carried_forward_days', 10, 2)->default(0);
                $table->timestamps();

                $table->unique(['employee_id', 'leave_type_id', 'fiscal_year_id']);
            });
        }

        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('days', 10, 2);
                $table->text('reason')->nullable();
                $table->string('status', 16)->default('pending');
                $table->json('approval_chain_json')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'status']);
                $table->index(['leave_type_id', 'start_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
