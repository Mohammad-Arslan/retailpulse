<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountResolutionType: string
{
    case FixedAccount = 'fixed_account';
    case AccountMapping = 'account_mapping';
    case CustomerReceivableAccount = 'customer_receivable_account';
    case SupplierPayableAccount = 'supplier_payable_account';
    case PaymentMethodAccount = 'payment_method_account';
    case BankAccount = 'bank_account';
    case WarehouseInventoryAccount = 'warehouse_inventory_account';
    case ProductCategoryAccount = 'product_category_account';
    case TaxAccount = 'tax_account';
    case ConfigurableMapping = 'configurable_mapping';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
