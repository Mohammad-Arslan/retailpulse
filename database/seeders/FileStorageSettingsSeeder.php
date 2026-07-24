<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Services\Storage\FileStorageDiskRegistrar;
use Illuminate\Database\Seeder;

/**
 * Seeds the "File Storage" settings group (Settings → File Storage) with this
 * project's actual MinIO connection details, instead of leaving it on the
 * factory default (disk=local, blank credentials).
 *
 * Only runs once: if an admin has already changed the disk away from 'local'
 * (via the Settings screen, or a previous run of this seeder), it's a no-op —
 * this must never overwrite deliberate admin configuration on a later
 * `db:seed --force` (the production entrypoint runs seeders on every boot).
 */
final class FileStorageSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $group = FileStorageDiskRegistrar::GROUP;

        if ((string) SystemSetting::get($group, 'disk', 'local') !== 'local') {
            return;
        }

        $endpoint = (string) env('MINIO_ENDPOINT', env('AWS_ENDPOINT', ''));

        SystemSetting::set($group, 'disk', 'minio', 'string');
        SystemSetting::set($group, 's3_region', (string) env('MINIO_REGION', 'us-east-1'), 'string');
        SystemSetting::set($group, 'minio_endpoint', $endpoint, 'string');
        SystemSetting::set($group, 'minio_bucket', (string) env('MINIO_BUCKET', env('AWS_BUCKET', 'retailpulse')), 'string');
        SystemSetting::set($group, 'minio_key', (string) env('MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID', '')), 'string');
        SystemSetting::set($group, 'minio_secret', (string) env('MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY', '')), 'encrypted');
        SystemSetting::set($group, 'minio_use_ssl', str_starts_with($endpoint, 'https://'), 'boolean');
        SystemSetting::set($group, 'minio_url', (string) env('MINIO_URL', env('AWS_URL', '')), 'string');
    }
}
