<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HrEmploymentType;
use App\Models\User;

final class HrEmploymentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.manage-settings') || $user->can('hr.manage-org');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.manage-settings');
    }

    public function update(User $user, HrEmploymentType $employmentType): bool
    {
        return $user->can('hr.manage-settings');
    }
}
