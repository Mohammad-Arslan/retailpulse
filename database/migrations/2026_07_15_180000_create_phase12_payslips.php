<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payslips')) {
            Schema::create('payslips', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_item_id')->unique()->constrained('payroll_items')->cascadeOnDelete();
                $table->string('payslip_number', 64)->unique();
                $table->string('disk', 32);
                $table->string('path');
                $table->timestamp('emailed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
