<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

final class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view') || $user->can('expenses.manage-categories');
    }

    public function view(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->can('expenses.view') || $user->can('expenses.manage-categories');
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.manage-categories');
    }

    public function update(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->can('expenses.manage-categories');
    }
}
