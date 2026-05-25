<?php

declare(strict_types=1);

namespace App\Enums;

enum PosCartStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Completing = 'completing';
    case Completed = 'completed';
    case Voided = 'voided';

    public function isOpen(): bool
    {
        return in_array($this, [self::Active, self::Suspended, self::Completing], true);
    }
}
