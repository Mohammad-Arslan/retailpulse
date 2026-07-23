<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')
            ->where('group', 'import_export')
            ->update(['group' => 'file_storage']);

        DB::table('system_settings')
            ->where('group', 'file_storage')
            ->where('key', 'local_root')
            ->update(['key' => 'import_export_local_root']);

        DB::table('permissions')
            ->where('name', 'settings.import-export.update')
            ->update(['name' => 'settings.file-storage.update']);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'settings.file-storage.update')
            ->update(['name' => 'settings.import-export.update']);

        DB::table('system_settings')
            ->where('group', 'file_storage')
            ->where('key', 'import_export_local_root')
            ->update(['key' => 'local_root']);

        DB::table('system_settings')
            ->where('group', 'file_storage')
            ->update(['group' => 'import_export']);
    }
};
