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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 128);
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'integer', 'boolean', 'json', 'encrypted'])->default('string');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['group', 'key']);
        });

        $now = now();
        $defaults = [
            ['disk', 'local', 'string'],
            ['local_root', 'import_exports', 'string'],
            ['s3_bucket', '', 'string'],
            ['s3_region', 'us-east-1', 'string'],
            ['s3_key', '', 'string'],
            ['s3_secret', '', 'encrypted'],
            ['s3_url', '', 'string'],
            ['minio_endpoint', '', 'string'],
            ['minio_bucket', '', 'string'],
            ['minio_key', '', 'string'],
            ['minio_secret', '', 'encrypted'],
            ['minio_use_ssl', 'true', 'boolean'],
            ['sftp_host', '', 'string'],
            ['sftp_user', '', 'string'],
            ['sftp_pass', '', 'encrypted'],
            ['sftp_key_path', '', 'string'],
            ['sftp_root', '/imports', 'string'],
            ['signed_url_ttl', '30', 'integer'],
            ['temp_file_ttl', '1440', 'integer'],
        ];

        foreach ($defaults as [$key, $value, $type]) {
            DB::table('system_settings')->insert([
                'group' => 'import_export',
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'updated_by' => null,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
