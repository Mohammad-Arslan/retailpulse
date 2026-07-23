<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_attachments', function (Blueprint $table): void {
            $table->string('disk', 32)->default('local')->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_attachments', function (Blueprint $table): void {
            $table->dropColumn('disk');
        });
    }
};
