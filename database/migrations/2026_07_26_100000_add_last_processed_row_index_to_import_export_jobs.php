<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_export_jobs', function (Blueprint $table) {
            $table->unsignedInteger('last_processed_row_index')->nullable()->after('skipped_rows');
        });
    }

    public function down(): void
    {
        Schema::table('import_export_jobs', function (Blueprint $table) {
            $table->dropColumn('last_processed_row_index');
        });
    }
};
