<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Storage;

use App\Models\SystemSetting;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportExportStorageManager
{
    private string $diskName;

    public function __construct()
    {
        $this->diskName = (string) SystemSetting::get('import_export', 'disk', 'local');
        config(['filesystems.disks.import_export' => $this->buildDiskConfig($this->diskName)]);
    }

    public function currentDisk(): string
    {
        return $this->diskName;
    }

    public function storeUpload(UploadedFile $file, string $directory): string
    {
        $filename = Str::ulid()->toString().'.'.$file->getClientOriginalExtension();

        return $file->storeAs($directory, $filename, $this->disk());
    }

    public function storeContent(string $content, string $path): string
    {
        Storage::disk('import_export')->put($path, $content);

        return $path;
    }

    /**
     * @param  resource  $stream
     */
    public function storeStream($stream, string $path): string
    {
        Storage::disk('import_export')->writeStream($path, $stream);

        return $path;
    }

    public function download(string $path): StreamedResponse
    {
        if (! $this->exists($path)) {
            abort(404, 'File not found.');
        }

        return $this->adapter()->download($path);
    }

    public function temporaryUrl(string $path, ?int $ttl = null): string
    {
        $ttl ??= (int) SystemSetting::get('import_export', 'signed_url_ttl', 30);

        if ($this->diskName === 'local') {
            return url()->temporarySignedRoute(
                'import-export.stream',
                now()->addMinutes($ttl),
                ['path' => encrypt($path)],
            );
        }

        return $this->adapter()->temporaryUrl($path, now()->addMinutes($ttl));
    }

    public function exists(string $path): bool
    {
        return Storage::disk('import_export')->exists($path);
    }

    public function delete(string $path): bool
    {
        return Storage::disk('import_export')->delete($path);
    }

    public function deleteDirectory(string $directory): bool
    {
        return Storage::disk('import_export')->deleteDirectory($directory);
    }

    public function size(string $path): int
    {
        return Storage::disk('import_export')->size($path);
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        $stream = Storage::disk('import_export')->readStream($path);

        if ($stream === false) {
            throw new \RuntimeException("Unable to read stream for path: {$path}");
        }

        return $stream;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDiskConfig(string $disk): array
    {
        return match ($disk) {
            's3' => $this->s3Config(),
            'minio' => $this->minioConfig(),
            'sftp' => $this->sftpConfig(),
            default => $this->localConfig(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function localConfig(): array
    {
        $root = (string) SystemSetting::get('import_export', 'local_root', 'import_exports');

        return [
            'driver' => 'local',
            'root' => storage_path('app/'.$root),
            'throw' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function s3Config(): array
    {
        return [
            'driver' => 's3',
            'key' => (string) SystemSetting::get('import_export', 's3_key', ''),
            'secret' => SystemSetting::getEncrypted('import_export', 's3_secret') ?? '',
            'region' => (string) SystemSetting::get('import_export', 's3_region', 'us-east-1'),
            'bucket' => (string) SystemSetting::get('import_export', 's3_bucket', ''),
            'url' => (string) SystemSetting::get('import_export', 's3_url', ''),
            'throw' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function minioConfig(): array
    {
        return [
            'driver' => 's3',
            'key' => (string) SystemSetting::get('import_export', 'minio_key', ''),
            'secret' => SystemSetting::getEncrypted('import_export', 'minio_secret') ?? '',
            'region' => (string) SystemSetting::get('import_export', 's3_region', 'us-east-1'),
            'bucket' => (string) SystemSetting::get('import_export', 'minio_bucket', ''),
            'endpoint' => (string) SystemSetting::get('import_export', 'minio_endpoint', ''),
            'use_path_style_endpoint' => true,
            'use_ssl' => (bool) SystemSetting::get('import_export', 'minio_use_ssl', true),
            'throw' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sftpConfig(): array
    {
        $config = [
            'driver' => 'sftp',
            'host' => (string) SystemSetting::get('import_export', 'sftp_host', ''),
            'username' => (string) SystemSetting::get('import_export', 'sftp_user', ''),
            'root' => (string) SystemSetting::get('import_export', 'sftp_root', '/imports'),
            'throw' => true,
        ];

        $keyPath = (string) SystemSetting::get('import_export', 'sftp_key_path', '');
        $password = SystemSetting::getEncrypted('import_export', 'sftp_pass');

        if ($keyPath !== '') {
            $config['privateKey'] = $keyPath;
        } elseif ($password !== null && $password !== '') {
            $config['password'] = $password;
        }

        return $config;
    }

    private function disk(): string
    {
        return 'import_export';
    }

    private function adapter(): FilesystemAdapter
    {
        $adapter = Storage::disk($this->disk());

        if (! $adapter instanceof FilesystemAdapter) {
            throw new \RuntimeException('Import export disk must resolve to a filesystem adapter.');
        }

        return $adapter;
    }
}
