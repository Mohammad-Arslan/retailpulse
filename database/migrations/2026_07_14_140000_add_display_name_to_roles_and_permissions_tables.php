<?php

declare(strict_types=1);

use App\Support\AccessControlLabels;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });

        foreach (DB::table('roles')->select('id', 'name')->cursor() as $role) {
            DB::table('roles')->where('id', $role->id)->update([
                'display_name' => AccessControlLabels::forRole((string) $role->name),
            ]);
        }

        foreach (DB::table('permissions')->select('id', 'name', 'description')->cursor() as $permission) {
            DB::table('permissions')->where('id', $permission->id)->update([
                'display_name' => AccessControlLabels::forPermission(
                    (string) $permission->name,
                    $permission->description !== null ? (string) $permission->description : null,
                ),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
