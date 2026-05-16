<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StorageConnectionTester
{
    public function __construct(
        private readonly ImportExportStorageManager $storageManager,
    ) {}

    public function test(): TestResult
    {
        $disk = $this->storageManager->currentDisk();

        try {
            $path = '_connection_test/'.Str::uuid()->toString().'.txt';
            $this->storageManager->storeContent('connection-test', $path);
            $exists = $this->storageManager->exists($path);
            $this->storageManager->delete($path);

            if (! $exists) {
                return TestResult::failure($disk, 'Could not verify written test file.');
            }

            return TestResult::success($disk);
        } catch (\Throwable $e) {
            return TestResult::failure($disk, $e->getMessage());
        }
    }
}
