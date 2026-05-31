<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->enum('method', ['cash', 'card', 'mobile_wallet', 'bank_transfer', 'credit']);
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('gateway_reference')->nullable();
            $table->json('meta')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sale_id', 'status']);
        });

        Schema::create('sale_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->string('template', 32);
            $table->string('pdf_path')->nullable();
            $table->uuid('public_token')->unique();
            $table->enum('fbr_status', [
                'not_applicable',
                'pending',
                'submitted',
                'failed',
                'blocked',
            ])->default('not_applicable');
            $table->string('fbr_invoice_number')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->index('sale_id');
        });

        Schema::create('sale_invoice_sequences', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->primary(['branch_id', 'date']);
        });

        Schema::create('payment_gateway_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->enum('mode', ['stub', 'live', 'disabled'])->default('stub');
            $table->json('credentials')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_configs');
        Schema::dropIfExists('sale_invoice_sequences');
        Schema::dropIfExists('sale_invoices');
        Schema::dropIfExists('sale_payments');
    }
};
