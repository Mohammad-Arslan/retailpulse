<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsTable = config('permission.table_names.permissions');

        Schema::create('user_permission_overrides', function (Blueprint $table) use ($permissionsTable) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('permission_id');
            $table->enum('type', ['grant', 'revoke']);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('permission_id')
                ->references('id')
                ->on($permissionsTable)
                ->cascadeOnDelete();

            $table->unique(['user_id', 'permission_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
