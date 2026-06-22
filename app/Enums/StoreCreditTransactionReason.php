<?php

declare(strict_types=1);

namespace App\Enums;

enum StoreCreditTransactionReason: string
{
    case Return = 'return';
    case Checkout = 'checkout';
    case Adjust = 'adjust';
    case Import = 'import';
}
