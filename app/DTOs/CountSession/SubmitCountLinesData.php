<?php

declare(strict_types=1);

namespace App\DTOs\CountSession;

/**
 * @phpstan-type CountLineInput array{line_id: int, counted_qty: int}
 */
final readonly class SubmitCountLinesData
{
    /**
     * @param  list<CountLineInput>  $lines
     */
    public function __construct(
        public array $lines,
        public int $userId,
    ) {}
}
