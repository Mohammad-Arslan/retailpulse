<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->string('description')->nullable()->after('guard_name');
            $table->boolean('is_system')->default(false)->after('description');
        });

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->string('group')->nullable()->index()->after('guard_name');
            $table->string('description')->nullable()->after('group');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropColumn(['description', 'is_system']);
        });

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->dropColumn(['group', 'description']);
        });
    }
};
