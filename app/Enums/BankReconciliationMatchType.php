<?php

declare(strict_types=1);

namespace App\Enums;

enum BankReconciliationMatchType: string
{
    case OneToOne = 'one_to_one';
    case OneToMany = 'one_to_many';
    case ManyToOne = 'many_to_one';
    case Partial = 'partial';
    case Adjustment = 'adjustment';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
