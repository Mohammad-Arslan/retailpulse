<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalEntryStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function isImmutable(): bool
    {
        return in_array($this, [self::Posted, self::Reversed], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
