<?php

declare(strict_types=1);

namespace App\DTOs\User;

final readonly class BranchAssignmentData
{
    /**
     * @param  list<array{branch_id: int, is_primary: bool}>  $assignments
     */
    public function __construct(
        public array $assignments,
    ) {}

    /**
     * @param  list<array{branch_id?: int, id?: int, is_primary?: bool}>|null  $input
     */
    public static function fromInput(?array $input): self
    {
        if ($input === null || $input === []) {
            return new self([]);
        }

        $assignments = [];
        $primarySet = false;

        foreach ($input as $row) {
            $branchId = (int) ($row['branch_id'] ?? $row['id'] ?? 0);

            if ($branchId <= 0) {
                continue;
            }

            $isPrimary = (bool) ($row['is_primary'] ?? false);

            if ($isPrimary) {
                $primarySet = true;
            }

            $assignments[] = [
                'branch_id' => $branchId,
                'is_primary' => $isPrimary,
            ];
        }

        if ($assignments !== [] && ! $primarySet) {
            $assignments[0]['is_primary'] = true;
        }

        return new self($assignments);
    }
}
