<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_hr_profiles')) {
            Schema::create('branch_hr_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
                $table->json('hr_enabled_modules')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table): void {
                $table->id();
                $table->string('employee_code', 64)->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->foreignId('primary_branch_id')->constrained('branches')->cascadeOnDelete();
                $table->unsignedBigInteger('salary_structure_id')->nullable()->index();
                $table->date('hire_date');
                $table->date('termination_date')->nullable();
                $table->string('employment_type', 32)->default('full_time');
                $table->foreignId('default_cost_centre_id')->nullable()->constrained('cost_centres')->nullOnDelete();
                $table->string('payment_method', 32)->nullable();
                $table->text('bank_details_encrypted')->nullable();
                $table->string('status', 16)->default('active');
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->nullable();
                $table->string('phone', 64)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('branch_hr_profiles');
    }
};
