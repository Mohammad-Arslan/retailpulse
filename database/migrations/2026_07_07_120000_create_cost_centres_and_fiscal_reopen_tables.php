<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cost_centres')) {
            Schema::create('cost_centres', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32);
                $table->string('name');
                $table->foreignId('parent_id')->nullable()->constrained('cost_centres')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('legal_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
                $table->string('status', 16)->default('active');
                $table->timestamps();
                $table->unique(['code', 'branch_id']);
            });
        }

        if (! Schema::hasTable('cost_centre_allocations')) {
            Schema::create('cost_centre_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_journal_transaction_id')->constrained('journal_transactions')->cascadeOnDelete();
                $table->foreignId('cost_centre_id')->constrained('cost_centres')->cascadeOnDelete();
                $table->string('allocation_method', 32)->default('manual');
                $table->decimal('allocation_percent', 8, 4)->nullable();
                $table->decimal('allocated_amount', 14, 2);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('journal_transactions', 'cost_centre_id')) {
            Schema::table('journal_transactions', function (Blueprint $table) {
                $table->foreignId('cost_centre_id')->nullable()->after('exchange_rate')->constrained('cost_centres')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('fiscal_year_reopen_requests')) {
            Schema::create('fiscal_year_reopen_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
                $table->text('reason');
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('first_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('second_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 32)->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journal_transactions', 'cost_centre_id')) {
            Schema::table('journal_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cost_centre_id');
            });
        }

        Schema::dropIfExists('fiscal_year_reopen_requests');
        Schema::dropIfExists('cost_centre_allocations');
        Schema::dropIfExists('cost_centres');
    }
};
