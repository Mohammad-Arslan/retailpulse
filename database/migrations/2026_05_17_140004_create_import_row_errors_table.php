<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_row_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')
                ->constrained('import_export_jobs')
                ->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->json('row_data')->nullable();
            $table->json('errors');
            $table->timestamp('created_at')->useCurrent();

            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_row_errors');
    }
};
