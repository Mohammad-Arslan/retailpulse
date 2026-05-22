<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_export_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->char('ulid', 26)->unique();
            $table->enum('type', ['import', 'export']);
            $table->string('entity_type', 64);
            $table->enum('mode', ['create', 'update', 'upsert', 'delete'])->nullable();
            $table->boolean('is_dry_run')->default(false);
            $table->string('input_file_path', 512)->nullable();
            $table->string('output_file_path', 512)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('disk', 32)->default('local');
            $table->enum('status', [
                'pending',
                'validating',
                'validated',
                'processing',
                'completing',
                'completed',
                'failed',
                'cancelled',
            ])->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->json('options')->nullable();
            $table->unsignedBigInteger('validation_profile_id')->nullable();
            $table->json('column_rules_snapshot')->nullable();
            $table->json('column_mapping')->nullable();
            $table->unsignedTinyInteger('step')->default(1);
            $table->json('file_preview')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['entity_type', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_export_jobs');
    }
};
