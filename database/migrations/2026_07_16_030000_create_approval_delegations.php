<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_delegations')) {
            return;
        }

        Schema::create('approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('to_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('scope', 32)->default('all');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['from_employee_id', 'scope', 'status']);
            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
    }
};
