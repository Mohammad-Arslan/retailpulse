<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_approval_settings')) {
            Schema::create('payroll_approval_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_entity_id')->unique()->constrained('organization_entities')->cascadeOnDelete();
                $table->boolean('requires_approval')->default(true);
                $table->decimal('approval_limit', 18, 4)->nullable();
                $table->boolean('use_workflow_engine')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_approval_settings');
    }
};
