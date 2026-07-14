<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recurring_expense_schedules')) {
            Schema::create('recurring_expense_schedules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->nullOnDelete();
                $table->char('currency_code', 3);
                $table->decimal('amount', 18, 4);
                $table->foreignId('tax_type_id')->nullable()->constrained('tax_types')->nullOnDelete();
                $table->string('frequency', 32);
                $table->unsignedInteger('interval_count')->default(1);
                $table->unsignedTinyInteger('day_of_period')->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('proration_policy', 32)->default('none');
                $table->timestamp('next_run_at');
                $table->string('payment_method', 32)->nullable();
                $table->string('status', 16)->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recurring_expense_occurrences')) {
            Schema::create('recurring_expense_occurrences', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('recurring_expense_schedule_id');
                $table->foreign('recurring_expense_schedule_id', 'reo_occurrences_schedule_fk')
                    ->references('id')
                    ->on('recurring_expense_schedules')
                    ->cascadeOnDelete();
                $table->string('period_key', 32);
                $table->date('scheduled_for');
                $table->decimal('amount', 18, 4);
                $table->decimal('functional_amount', 18, 4)->nullable();
                $table->string('status', 16)->default('pending');
                $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
                $table->foreignId('accounting_event_id')->nullable()->constrained('accounting_events')->nullOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['recurring_expense_schedule_id', 'period_key'], 'recurring_expense_occurrences_schedule_period_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_occurrences');
        Schema::dropIfExists('recurring_expense_schedules');
    }
};
