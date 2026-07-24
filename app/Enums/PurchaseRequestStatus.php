<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Converted = 'converted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canEdit(): bool
    {
        return $this === self::Draft;
    }

    public function canSubmit(): bool
    {
        return $this === self::Draft;
    }

    public function canApprove(): bool
    {
        return $this === self::Submitted;
    }

    public function canReject(): bool
    {
        return $this === self::Submitted;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::Approved], true);
    }

    public function canConvert(): bool
    {
        return $this === self::Approved;
    }
}
