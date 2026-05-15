<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identifier_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('key', 64)->unique();
            $table->string('identifier_type', 32);
            $table->string('format', 32)->default('internal');
            $table->string('prefix', 32)->default('');
            $table->string('suffix', 32)->default('');
            $table->unsignedSmallInteger('pad_length')->default(6);
            $table->unsignedBigInteger('last_value')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identifier_sequences');
    }
};
