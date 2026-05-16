<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_column_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->constrained('import_validation_profiles')
                ->cascadeOnDelete();
            $table->string('column_key', 128);
            $table->string('mapped_to', 128)->nullable();
            $table->string('display_label', 128)->nullable();
            $table->json('rules');
            $table->boolean('is_required')->default(false);
            $table->string('default_value', 512)->nullable();
            $table->json('transform')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_column_rules');
    }
};
