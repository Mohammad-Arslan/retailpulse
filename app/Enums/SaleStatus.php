<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case PartiallyPaid = 'partially_paid';
    case Completed = 'completed';
    case Voided = 'voided';
    case Refunded = 'refunded';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::PendingPayment, self::Voided], true),
            self::PendingPayment => in_array($target, [self::Completed, self::PartiallyPaid, self::Voided], true),
            self::PartiallyPaid => in_array($target, [self::Completed, self::Voided], true),
            self::Completed => $target === self::Refunded,
            self::Voided, self::Refunded => false,
        };
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::PendingPayment, self::PartiallyPaid], true);
    }

    public function isImmutable(): bool
    {
        return in_array($this, [self::Completed, self::Refunded], true);
    }
}
