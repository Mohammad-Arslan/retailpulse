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
    case Wallet = 'wallet';
    case StoreCredit = 'store_credit';

    public function requiresGateway(): bool
    {
        return in_array($this, [self::Card, self::MobileWallet], true);
    }

    public function requiresCustomer(): bool
    {
        return in_array($this, [self::Credit, self::Wallet, self::StoreCredit], true);
    }
}
