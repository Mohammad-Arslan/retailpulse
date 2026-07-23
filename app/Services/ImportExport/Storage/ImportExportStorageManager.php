<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Storage;

use App\Models\SystemSetting;
use App\Services\Storage\FileStorageDiskRegistrar;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportExportStorageManager
{
    private string $diskName;

    public function __construct(
        private readonly FileStorageDiskRegistrar $storage,
    ) {
        $this->diskName = $this->storage->diskType();
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
        $path = trim($path);

        if ($path === '') {
            throw new \InvalidArgumentException('Cannot generate a download URL for an empty file path.');
        }

        if (! $this->exists($path)) {
            throw new \InvalidArgumentException("Cannot generate a download URL; file not found: {$path}");
        }

        $ttl ??= (int) SystemSetting::get(FileStorageDiskRegistrar::GROUP, 'signed_url_ttl', 30);

        if ($this->diskName === 'local') {
            return url()->temporarySignedRoute(
                'admin.import-export.stream',
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
        if ($disk === 'local') {
            return $this->localConfig();
        }

        $prefix = (string) SystemSetting::get(FileStorageDiskRegistrar::GROUP, 'import_export_prefix', 'import_exports');

        return $this->storage->remoteDiskConfig($prefix);
    }

    /**
     * @return array<string, mixed>
     */
    private function localConfig(): array
    {
        $root = (string) SystemSetting::get(FileStorageDiskRegistrar::GROUP, 'import_export_local_root', 'import_exports');

        return [
            'driver' => 'local',
            'root' => storage_path('app/'.$root),
            'throw' => true,
        ];
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
