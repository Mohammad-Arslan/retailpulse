<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\ImportExport\Storage\ImportExportStorageManager;
use Illuminate\Http\UploadedFile;

trait HandlesImportExportStorage
{
    protected function storageManager(): ImportExportStorageManager
    {
        return app(ImportExportStorageManager::class);
    }

    public function storeImportFile(UploadedFile $file, string $entityType): string
    {
        $directory = sprintf(
            'imports/%s/%s/%s',
            $entityType,
            now()->format('Y'),
            now()->format('m'),
        );

        return $this->storageManager()->storeUpload($file, $directory);
    }

    public function storeExportFile(string $content, string $entityType, string $extension = 'xlsx'): string
    {
        $path = sprintf(
            'exports/%s/%s/%s/%s.%s',
            $entityType,
            now()->format('Y'),
            now()->format('m'),
            \Illuminate\Support\Str::ulid(),
            $extension,
        );

        return $this->storageManager()->storeContent($content, $path);
    }

    /**
     * @param  resource  $stream
     */
    public function storeExportStream($stream, string $entityType, string $extension = 'xlsx'): string
    {
        $path = sprintf(
            'exports/%s/%s/%s/%s.%s',
            $entityType,
            now()->format('Y'),
            now()->format('m'),
            \Illuminate\Support\Str::ulid(),
            $extension,
        );

        return $this->storageManager()->storeStream($stream, $path);
    }

    public function storeErrorReport(string $content, string $jobUlid): string
    {
        $path = "errors/{$jobUlid}/error-report.xlsx";

        return $this->storageManager()->storeContent($content, $path);
    }

    public function importFileTemporaryUrl(string $path): string
    {
        return $this->storageManager()->temporaryUrl($path);
    }

    public function exportFileTemporaryUrl(string $path): string
    {
        return $this->storageManager()->temporaryUrl($path);
    }

    public function deleteImportFile(string $path): bool
    {
        return $this->storageManager()->delete($path);
    }

    public function cleanupJobFiles(string $jobUlid): bool
    {
        return $this->storageManager()->deleteDirectory("errors/{$jobUlid}");
    }

    /**
     * @return resource
     */
    public function readImportStream(string $path)
    {
        return $this->storageManager()->readStream($path);
    }

    public function importFileExists(string $path): bool
    {
        return $this->storageManager()->exists($path);
    }
}
