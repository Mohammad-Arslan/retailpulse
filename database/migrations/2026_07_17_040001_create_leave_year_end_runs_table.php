<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_year_end_runs')) {
            Schema::create('leave_year_end_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
                $table->string('period_label', 64);
                $table->string('status', 16)->default('completed');
                $table->json('totals_json')->nullable();
                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('executed_at')->nullable();
                $table->timestamps();

                $table->unique(['legal_entity_id', 'period_label'], 'leave_year_end_runs_entity_period_unique');
            });
        }

        if (! Schema::hasTable('leave_year_end_lines')) {
            Schema::create('leave_year_end_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('leave_year_end_run_id')->constrained('leave_year_end_runs')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
                $table->decimal('carried_forward', 10, 2)->default(0);
                $table->decimal('expired', 10, 2)->default(0);
                $table->decimal('encashed', 10, 2)->default(0);
                $table->decimal('next_opening', 10, 2)->default(0);
                $table->timestamps();

                $table->index(['employee_id', 'leave_type_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_year_end_lines');
        Schema::dropIfExists('leave_year_end_runs');
    }
};
