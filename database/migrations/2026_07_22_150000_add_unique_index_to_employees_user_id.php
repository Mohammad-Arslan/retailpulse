<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateUserIds = DB::table('employees')
            ->select('user_id')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        if ($duplicateUserIds->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add unique index on employees.user_id: multiple employees are already '
                .'linked to the same user_id ('.$duplicateUserIds->implode(', ').'). '
                .'Resolve the duplicate links before running this migration.',
            );
        }

        Schema::table('employees', function (Blueprint $table): void {
            $table->unique('user_id', 'employees_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique('employees_user_id_unique');
        });
    }
};
