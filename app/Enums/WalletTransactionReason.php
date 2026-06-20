<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletTransactionReason: string
{
    case TopUp = 'top_up';
    case Checkout = 'checkout';
    case Refund = 'refund';
    case Adjust = 'adjust';
    case Expiry = 'expiry';
    case Import = 'import';
}
