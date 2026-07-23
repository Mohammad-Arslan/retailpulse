<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\SystemSetting;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

final class FileStorageDiskRegistrar
{
    public const string GROUP = 'file_storage';

    /**
     * purpose => [dynamic disk name, prefix setting key, disk used when the admin setting is 'local'].
     *
     * @var array<string, array{disk: string, prefix_key: string, local_disk: string}>
     */
    private const array PURPOSES = [
        'media' => ['disk' => 'media', 'prefix_key' => 'media_prefix', 'local_disk' => 'public'],
        'supplier_attachments' => ['disk' => 'supplier_attachments', 'prefix_key' => 'supplier_attachments_prefix', 'local_disk' => 'local'],
        'expense_attachments' => ['disk' => 'expense_attachments', 'prefix_key' => 'expense_attachments_prefix', 'local_disk' => 'local'],
    ];

    private ?string $diskType = null;

    /** @var array<string, true> */
    private array $registered = [];

    public function diskType(): string
    {
        return $this->diskType ??= (string) SystemSetting::get(self::GROUP, 'disk', 'local');
    }

    /**
     * The Laravel disk name a NEW write for this purpose should use right now.
     */
    public function diskNameFor(string $purpose): string
    {
        $config = self::PURPOSES[$purpose] ?? null;

        if ($config === null) {
            throw new \InvalidArgumentException("Unknown file storage purpose: {$purpose}");
        }

        if ($this->diskType() === 'local') {
            return $config['local_disk'];
        }

        $this->ensureRegistered($config['disk']);

        return $config['disk'];
    }

    /**
     * The one call site every reader (accessors, controllers, jobs) should use instead of
     * a bare Storage::disk() — guarantees a dynamically-managed disk is registered first.
     */
    public function resolve(string $diskName): FilesystemAdapter
    {
        $this->ensureRegistered($diskName);

        $adapter = Storage::disk($diskName);

        if (! $adapter instanceof FilesystemAdapter) {
            throw new \RuntimeException("Disk [{$diskName}] must resolve to a filesystem adapter.");
        }

        return $adapter;
    }

    /**
     * Idempotent per-request. No-op for legacy static disk names (public/local/s3/minio)
     * and for 'import_export', which registers itself via ImportExportStorageManager.
     */
    public function ensureRegistered(string $diskName): void
    {
        if (isset($this->registered[$diskName])) {
            return;
        }

        $purpose = $this->purposeForDiskName($diskName);

        if ($purpose === null) {
            return;
        }

        $prefix = (string) SystemSetting::get(self::GROUP, self::PURPOSES[$purpose]['prefix_key'], $diskName);

        config(["filesystems.disks.{$diskName}" => $this->remoteDiskConfig($prefix)]);
        $this->registered[$diskName] = true;
    }

    public function forgetRegistrations(): void
    {
        $this->diskType = null;
        $this->registered = [];
    }

    /**
     * Shared S3/MinIO/SFTP disk-config builder keyed off the common `file_storage`
     * credentials — used for every dynamically-registered disk here, and by
     * ImportExportStorageManager for its own remote-mode disk.
     *
     * @return array<string, mixed>
     */
    public function remoteDiskConfig(string $prefix): array
    {
        return match ($this->diskType()) {
            's3' => $this->s3Config($prefix),
            'minio' => $this->minioConfig($prefix),
            'sftp' => $this->sftpConfig($prefix),
            default => throw new \RuntimeException("remoteDiskConfig() called while the file storage disk type is 'local'."),
        };
    }

    private function purposeForDiskName(string $diskName): ?string
    {
        foreach (self::PURPOSES as $purpose => $config) {
            if ($config['disk'] === $diskName) {
                return $purpose;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function s3Config(string $prefix): array
    {
        return [
            'driver' => 's3',
            'key' => (string) SystemSetting::get(self::GROUP, 's3_key', ''),
            'secret' => SystemSetting::getEncrypted(self::GROUP, 's3_secret') ?? '',
            'region' => (string) SystemSetting::get(self::GROUP, 's3_region', 'us-east-1'),
            'bucket' => (string) SystemSetting::get(self::GROUP, 's3_bucket', ''),
            'url' => (string) SystemSetting::get(self::GROUP, 's3_url', ''),
            'root' => trim($prefix, '/'),
            'throw' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function minioConfig(string $prefix): array
    {
        return [
            'driver' => 's3',
            'key' => (string) SystemSetting::get(self::GROUP, 'minio_key', ''),
            'secret' => SystemSetting::getEncrypted(self::GROUP, 'minio_secret') ?? '',
            'region' => (string) SystemSetting::get(self::GROUP, 's3_region', 'us-east-1'),
            'bucket' => (string) SystemSetting::get(self::GROUP, 'minio_bucket', ''),
            'endpoint' => (string) SystemSetting::get(self::GROUP, 'minio_endpoint', ''),
            'use_path_style_endpoint' => true,
            'use_ssl' => (bool) SystemSetting::get(self::GROUP, 'minio_use_ssl', true),
            'root' => trim($prefix, '/'),
            'throw' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sftpConfig(string $prefix): array
    {
        $root = (string) SystemSetting::get(self::GROUP, 'sftp_root', '/imports');

        $config = [
            'driver' => 'sftp',
            'host' => (string) SystemSetting::get(self::GROUP, 'sftp_host', ''),
            'username' => (string) SystemSetting::get(self::GROUP, 'sftp_user', ''),
            'root' => rtrim($root, '/').'/'.trim($prefix, '/'),
            'throw' => true,
        ];

        $keyPath = (string) SystemSetting::get(self::GROUP, 'sftp_key_path', '');
        $password = SystemSetting::getEncrypted(self::GROUP, 'sftp_pass');

        if ($keyPath !== '') {
            $config['privateKey'] = $keyPath;
        } elseif ($password !== null && $password !== '') {
            $config['password'] = $password;
        }

        return $config;
    }
}
