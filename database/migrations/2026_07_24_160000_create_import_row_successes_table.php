<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_row_successes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')
                ->constrained('import_export_jobs')
                ->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['job_id', 'row_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_row_successes');
    }
};
