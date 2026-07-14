<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\User;

final class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.view')
            || $user->can('accounting.create-journal')
            || $user->can('accounting.post-journal')
            || $user->can('accounting.reverse-journal');
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.create-journal');
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can('accounting.create-journal')
            && $journalEntry->status === JournalEntryStatus::Draft;
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can('accounting.create-journal')
            && $journalEntry->status === JournalEntryStatus::Draft;
    }

    public function post(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can('accounting.post-journal');
    }

    public function approve(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can('accounting.approve-journal');
    }

    public function reverse(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can('accounting.reverse-journal');
    }
}
