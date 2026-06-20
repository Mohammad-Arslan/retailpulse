<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case MobileWallet = 'mobile_wallet';
    case BankTransfer = 'bank_transfer';
    case Credit = 'credit';

    public function requiresGateway(): bool
    {
        return in_array($this, [self::Card, self::MobileWallet], true);
    }
}
