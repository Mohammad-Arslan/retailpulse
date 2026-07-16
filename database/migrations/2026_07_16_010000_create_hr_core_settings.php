<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_employment_types')) {
            Schema::create('hr_employment_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->string('code', 64);
                $table->string('name');
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->unique(['legal_entity_id', 'code']);
            });
        }

        if (! Schema::hasTable('hr_entity_settings')) {
            Schema::create('hr_entity_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_entity_id')->unique()->constrained('organization_entities')->cascadeOnDelete();
                $table->foreignId('default_holiday_calendar_id')->nullable()->constrained('holiday_calendars')->nullOnDelete();
                $table->string('employee_code_sequence_key', 64)->nullable();
                $table->json('settings_json')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_entity_settings');
        Schema::dropIfExists('hr_employment_types');
    }
};
