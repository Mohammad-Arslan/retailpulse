<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $create = static function (string $table, callable $callback): void {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $callback);
            }
        };

        $create('coa_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->json('validation_summary')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        $create('opening_balance_import_batches', function (Blueprint $table) {
            $table->id();
            $table->date('cutover_date');
            $table->string('file_name');
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->json('validation_summary')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        if (Schema::hasTable('opening_balance_import_lines')) {
            Schema::dropIfExists('opening_balance_import_lines');
        }

        Schema::create('opening_balance_import_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opening_balance_import_batch_id');
            $table->foreign('opening_balance_import_batch_id', 'ob_import_lines_batch_fk')
                ->references('id')
                ->on('opening_balance_import_batches')
                ->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('party_type', 128)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedBigInteger('cost_centre_id')->nullable();
            $table->string('validation_status', 32)->default('valid');
            $table->text('validation_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_import_lines');
        Schema::dropIfExists('opening_balance_import_batches');
        Schema::dropIfExists('coa_import_batches');
    }
};
