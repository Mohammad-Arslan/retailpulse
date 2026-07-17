<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_encashments')) {
            Schema::create('leave_encashments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
                $table->foreignId('leave_policy_id')->constrained('leave_policies')->cascadeOnDelete();
                $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
                $table->decimal('days', 10, 2);
                $table->string('payroll_component_code', 64)->nullable();
                $table->text('reason')->nullable();
                $table->string('status', 16)->default('pending');
                $table->timestamp('approved_at')->nullable();
                $table->json('approval_chain_json')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'status']);
                $table->index(['leave_type_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_encashments');
    }
};
