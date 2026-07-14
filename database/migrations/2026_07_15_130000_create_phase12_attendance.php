<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_sources')) {
            Schema::create('attendance_sources', function (Blueprint $table): void {
                $table->id();
                $table->string('driver', 32);
                $table->string('name');
                $table->json('config_json')->nullable();
                $table->string('status', 16)->default('active');
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->timestamps();

                $table->index(['driver', 'status']);
            });
        }

        if (! Schema::hasTable('attendance_records')) {
            Schema::create('attendance_records', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('source_id')->constrained('attendance_sources')->restrictOnDelete();
                $table->timestamp('clock_in');
                $table->timestamp('clock_out')->nullable();
                $table->unsignedInteger('worked_minutes')->nullable();
                $table->string('status', 16)->default('open');
                $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('adjustment_reason')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'status']);
                $table->index(['branch_id', 'clock_in']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sources');
    }
};
