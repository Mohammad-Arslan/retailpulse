<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->string('code', 64);
                $table->string('name');
                $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->nullOnDelete();
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->unique(['legal_entity_id', 'code']);
            });
        }

        if (! Schema::hasTable('grades')) {
            Schema::create('grades', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->string('code', 64);
                $table->string('name');
                $table->unsignedSmallInteger('rank')->default(0);
                $table->string('currency_code', 3)->nullable();
                $table->decimal('min_amount', 18, 4)->nullable();
                $table->decimal('mid_amount', 18, 4)->nullable();
                $table->decimal('max_amount', 18, 4)->nullable();
                $table->boolean('enforce_salary_band')->default(false);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('status', 16)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('designations')) {
            Schema::create('designations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->string('code', 64);
                $table->string('name');
                $table->foreignId('default_grade_id')->nullable()->constrained('grades')->nullOnDelete();
                $table->string('status', 16)->default('active');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('departments');
    }
};
