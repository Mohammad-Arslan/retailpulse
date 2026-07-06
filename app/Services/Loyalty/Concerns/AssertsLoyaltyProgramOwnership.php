<?php

declare(strict_types=1);

namespace App\Services\Loyalty\Concerns;

use App\Models\LoyaltyProgram;

trait AssertsLoyaltyProgramOwnership
{
    private function assertBelongsToProgram(int $programId, LoyaltyProgram $program): void
    {
        if ($programId !== $program->id) {
            abort(404);
        }
    }
}
