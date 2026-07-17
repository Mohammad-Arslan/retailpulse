<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('toil_claims')) {
            Schema::create('toil_claims', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('claim_type', 8);
                $table->decimal('hours', 8, 2);
                $table->string('status', 16)->default('pending');
                $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->cascadeOnDelete();
                $table->string('payroll_component_code', 64)->nullable();
                $table->text('reason')->nullable();
                $table->json('approval_chain_json')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'status']);
                $table->index(['claim_type', 'status']);
            });
        }

        if (! Schema::hasTable('toil_ledger_entries')) {
            Schema::create('toil_ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('entry_type', 16);
                $table->decimal('hours', 8, 2);
                $table->date('earned_date')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->foreignId('overtime_record_id')->nullable()->constrained('overtime_records')->nullOnDelete();
                $table->foreignId('toil_claim_id')->nullable()->constrained('toil_claims')->nullOnDelete();
                $table->foreignId('credit_entry_id')->nullable()->constrained('toil_ledger_entries')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['employee_id', 'entry_type']);
                $table->index(['employee_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('toil_balances')) {
            Schema::create('toil_balances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
                $table->decimal('available_hours', 8, 2)->default(0);
                $table->decimal('pending_hours', 8, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('toil_balances');
        Schema::dropIfExists('toil_ledger_entries');
        Schema::dropIfExists('toil_claims');
    }
};
