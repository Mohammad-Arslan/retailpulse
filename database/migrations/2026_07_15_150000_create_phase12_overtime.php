<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('overtime_policies')) {
            Schema::create('overtime_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->unsignedInteger('daily_threshold_minutes');
                $table->unsignedInteger('weekly_threshold_minutes')->nullable();
                $table->boolean('rest_day_applies')->default(false);
                $table->boolean('public_holiday_applies')->default(false);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 16)->default('active');
                $table->unsignedSmallInteger('priority')->default(100);
                $table->timestamps();

                $table->index(['legal_entity_id', 'branch_id', 'status']);
            });
        }

        if (! Schema::hasTable('overtime_multipliers')) {
            Schema::create('overtime_multipliers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('overtime_policy_id')->constrained('overtime_policies')->cascadeOnDelete();
                $table->string('day_type', 32);
                $table->decimal('multiplier', 8, 4);
                $table->timestamps();

                $table->unique(['overtime_policy_id', 'day_type']);
            });
        }

        if (! Schema::hasTable('overtime_records')) {
            Schema::create('overtime_records', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('date');
                $table->unsignedInteger('regular_minutes')->default(0);
                $table->unsignedInteger('overtime_minutes')->default(0);
                $table->string('day_type', 32);
                $table->decimal('resolved_multiplier', 8, 4);
                $table->foreignId('overtime_policy_id')->constrained('overtime_policies')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 16)->default('pending');
                $table->timestamps();

                $table->unique(['employee_id', 'date']);
                $table->index(['employee_id', 'status', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_records');
        Schema::dropIfExists('overtime_multipliers');
        Schema::dropIfExists('overtime_policies');
    }
};
