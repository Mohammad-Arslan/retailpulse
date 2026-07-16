<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrgAssignmentChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Employee $employee,
        public readonly string $field,
        public readonly ?string $oldValue,
        public readonly ?string $newValue,
        public readonly string $effectiveFrom,
    ) {}
}
