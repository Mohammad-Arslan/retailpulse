<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

final readonly class ImportRowResult
{
    public function __construct(
        public bool $success,
        public mixed $recordId = null,
        public ?string $message = null,
    ) {}

    public static function success(mixed $recordId): self
    {
        return new self(success: true, recordId: $recordId);
    }

    public static function failure(string $message): self
    {
        return new self(success: false, message: $message);
    }
}
