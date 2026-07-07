<?php

declare(strict_types=1);

namespace App\Enums;

enum AmountSource: string
{
    case GrossAmount = 'gross_amount';
    case NetAmount = 'net_amount';
    case TaxAmount = 'tax_amount';
    case DiscountAmount = 'discount_amount';
    case ShippingAmount = 'shipping_amount';
    case InventoryCost = 'inventory_cost';
    case LandedCost = 'landed_cost';
    case SettlementAmount = 'settlement_amount';
    case ExchangeDifference = 'exchange_difference';
    case DepreciationAmount = 'depreciation_amount';
    case CustomFormula = 'custom_formula';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
