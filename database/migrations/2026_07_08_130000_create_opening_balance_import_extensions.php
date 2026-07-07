<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('opening_balance_import_batches')) {
            Schema::table('opening_balance_import_batches', function (Blueprint $table) {
                if (! Schema::hasColumn('opening_balance_import_batches', 'batch_type')) {
                    $table->string('batch_type', 32)->default('full_gl')->after('file_name');
                }

                if (! Schema::hasColumn('opening_balance_import_batches', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }

                if (! Schema::hasColumn('opening_balance_import_batches', 'imported_at')) {
                    $table->timestamp('imported_at')->nullable()->after('approved_at');
                }
            });
        }

        if (! Schema::hasTable('opening_balance_reconciliations')) {
            Schema::create('opening_balance_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('opening_balance_import_batch_id');
                $table->foreign('opening_balance_import_batch_id', 'ob_recon_batch_fk')
                    ->references('id')
                    ->on('opening_balance_import_batches')
                    ->cascadeOnDelete();
                $table->string('reconciliation_type', 32);
                $table->decimal('source_total', 14, 2)->default(0);
                $table->decimal('import_total', 14, 2)->default(0);
                $table->decimal('variance', 14, 2)->default(0);
                $table->string('status', 32)->default('pending');
                $table->foreignId('variance_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('coa_import_lines')) {
            Schema::create('coa_import_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coa_import_batch_id')->constrained('coa_import_batches')->cascadeOnDelete();
                $table->string('code', 32);
                $table->string('name');
                $table->string('type', 16);
                $table->string('parent_code', 32)->nullable();
                $table->boolean('is_group')->default(false);
                $table->boolean('is_postable')->default(true);
                $table->string('branch_code', 32)->nullable();
                $table->string('currency_code', 3)->nullable();
                $table->string('status', 16)->default('pending');
                $table->string('validation_status', 16)->default('pending');
                $table->text('validation_message')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coa_import_lines');
        Schema::dropIfExists('opening_balance_reconciliations');

        if (Schema::hasTable('opening_balance_import_batches')) {
            Schema::table('opening_balance_import_batches', function (Blueprint $table) {
                foreach (['imported_at', 'approved_at', 'batch_type'] as $column) {
                    if (Schema::hasColumn('opening_balance_import_batches', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
