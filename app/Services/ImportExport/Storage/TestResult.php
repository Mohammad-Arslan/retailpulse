<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Storage;

final readonly class TestResult
{
    public function __construct(
        public bool $success,
        public string $disk,
        public ?string $error = null,
    ) {}

    public static function success(string $disk): self
    {
        return new self(success: true, disk: $disk);
    }

    public static function failure(string $disk, string $error): self
    {
        return new self(success: false, disk: $disk, error: $error);
    }
}
