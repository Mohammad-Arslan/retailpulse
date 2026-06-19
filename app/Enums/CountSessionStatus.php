<?php

declare(strict_types=1);

namespace App\Enums;

enum CountSessionStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Posted = 'posted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::InProgress], true);
    }

    public function canPost(): bool
    {
        return $this === self::Approved;
    }
}
