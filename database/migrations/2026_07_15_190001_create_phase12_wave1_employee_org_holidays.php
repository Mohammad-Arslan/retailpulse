<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('primary_branch_id')->constrained('departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'designation_id')) {
                $table->foreignId('designation_id')->nullable()->after('department_id')->constrained('designations')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'grade_id')) {
                $table->foreignId('grade_id')->nullable()->after('designation_id')->constrained('grades')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'reporting_manager_employee_id')) {
                $table->foreignId('reporting_manager_employee_id')->nullable()->after('grade_id')->constrained('employees')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'probation_end_date')) {
                $table->date('probation_end_date')->nullable()->after('termination_date');
            }
            if (! Schema::hasColumn('employees', 'confirmation_date')) {
                $table->date('confirmation_date')->nullable()->after('probation_end_date');
            }
        });

        if (! Schema::hasTable('employee_manager_history')) {
            Schema::create('employee_manager_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_assignment_history')) {
            Schema::create('employee_assignment_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('field_name', 64);
                $table->string('old_value', 255)->nullable();
                $table->string('new_value', 255)->nullable();
                $table->date('effective_from');
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('holiday_calendars')) {
            Schema::create('holiday_calendars', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('status', 16)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('holiday_dates')) {
            Schema::create('holiday_dates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('holiday_calendar_id')->constrained('holiday_calendars')->cascadeOnDelete();
                $table->date('holiday_date');
                $table->string('name');
                $table->string('holiday_type', 32)->default('public');
                $table->boolean('is_paid')->default(true);
                $table->timestamps();

                $table->unique(['holiday_calendar_id', 'holiday_date']);
            });
        }

        if (! Schema::hasTable('holiday_calendar_assignments')) {
            Schema::create('holiday_calendar_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('holiday_calendar_id')->constrained('holiday_calendars')->cascadeOnDelete();
                $table->string('assignable_type', 64);
                $table->unsignedBigInteger('assignable_id');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->unsignedSmallInteger('priority')->default(0);
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->index(['assignable_type', 'assignable_id'], 'holiday_assignments_assignable_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_calendar_assignments');
        Schema::dropIfExists('holiday_dates');
        Schema::dropIfExists('holiday_calendars');
        Schema::dropIfExists('employee_assignment_history');
        Schema::dropIfExists('employee_manager_history');

        Schema::table('employees', function (Blueprint $table): void {
            $columns = [
                'department_id',
                'designation_id',
                'grade_id',
                'reporting_manager_employee_id',
                'probation_end_date',
                'confirmation_date',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
