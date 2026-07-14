<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

final class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can('expenses.create');
    }

    public function approve(User $user, Expense $expense): bool
    {
        return $user->can('expenses.approve') || $user->can('expenses.post');
    }

    public function attachReceipt(User $user, Expense $expense): bool
    {
        return $user->can('expenses.create');
    }
}
