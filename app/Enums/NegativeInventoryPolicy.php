<?php

declare(strict_types=1);

namespace App\Enums;

enum NegativeInventoryPolicy: string
{
    case Strict = 'strict';
    case Allow = 'allow';
    case ApprovalRequired = 'approval_required';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
