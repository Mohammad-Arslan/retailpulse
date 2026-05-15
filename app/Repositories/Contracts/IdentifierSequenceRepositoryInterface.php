<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\IdentifierType;
use App\Models\IdentifierSequence;

interface IdentifierSequenceRepositoryInterface
{
    public function lockByKey(string $key): ?IdentifierSequence;

    public function createFromConfig(IdentifierType $type, string $key): IdentifierSequence;
}
