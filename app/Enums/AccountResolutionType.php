<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The spec lists 13 resolution types; two are deliberately absent, not overlooked:
 * - employee_payable_account: no payroll module exists yet (Phase 12).
 * - intercompany_account: Intercompany is config-gated behind multi_currency and
 *   deferred per config/accounting_modules.php.
 */
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
    case AssetAccount = 'asset_account';
    /** Resolves via payload expense_account_mapping_key (or line mapping key), then AccountResolverService. */
    case ExpenseCategoryAccount = 'expense_category_account';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
