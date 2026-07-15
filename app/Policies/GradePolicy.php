<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

final class GradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.view-employees') || $user->can('hr.manage-org');
    }

    public function view(User $user, Grade $grade): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.manage-org');
    }

    public function update(User $user, Grade $grade): bool
    {
        return $user->can('hr.manage-org');
    }
}
