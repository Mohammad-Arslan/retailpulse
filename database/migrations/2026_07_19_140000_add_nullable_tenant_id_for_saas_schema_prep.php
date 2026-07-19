<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'account_mappings',
        'accounting_events',
        'approval_delegations',
        'ar_aging_snapshots',
        'asset_categories',
        'attendance_records',
        'attendance_sources',
        'audit_logs',
        'bank_accounts',
        'bank_reconciliation_matches',
        'bank_statement_lines',
        'bin_locations',
        'branch_accounting_profiles',
        'branch_hr_profiles',
        'branch_product_prices',
        'chart_of_accounts',
        'cheques',
        'coa_import_batches',
        'coa_import_lines',
        'cost_centre_allocations',
        'cost_centres',
        'count_schedule_rules',
        'count_session_lines',
        'count_sessions',
        'credit_notes',
        'customer_ar_ledger',
        'customer_groups',
        'customer_loyalty_events',
        'customer_reminder_logs',
        'customer_wallet_transactions',
        'customer_wallets',
        'customer_write_offs',
        'customers',
        'debit_notes',
        'departments',
        'designations',
        'document_sequences',
        'employee_assignment_history',
        'employee_attachments',
        'employee_bank_accounts',
        'employee_branch_assignments',
        'employee_dependents',
        'employee_manager_history',
        'employee_medical_profiles',
        'employee_profiles',
        'employee_shift_preferences',
        'employees',
        'expense_approval_policies',
        'expense_attachments',
        'expense_categories',
        'expenses',
        'fbr_invoice_queue',
        'fbr_invoice_sequences',
        'fiscal_year_reopen_requests',
        'fiscal_years',
        'fixed_assets',
        'goods_receiving_notes',
        'grades',
        'grn_items',
        'holiday_calendars',
        'holiday_dates',
        'hr_employment_types',
        'hr_entity_settings',
        'images',
        'import_column_rules',
        'import_row_errors',
        'intercompany_transactions',
        'inventories',
        'inventory_cost_layers',
        'journal_entries',
        'journal_transactions',
        'landed_cost_allocations',
        'landed_cost_entries',
        'leave_encashments',
        'leave_entitlements',
        'leave_policies',
        'leave_request_reschedules',
        'leave_requests',
        'leave_types',
        'leave_year_end_lines',
        'leave_year_end_runs',
        'loyalty_approval_policies',
        'loyalty_campaigns',
        'loyalty_expiry_rules',
        'loyalty_points',
        'loyalty_program_tiers',
        'loyalty_rules',
        'loyalty_tiers',
        'opening_balance_import_batches',
        'opening_balance_import_lines',
        'opening_balance_reconciliations',
        'overtime_multipliers',
        'overtime_policies',
        'overtime_records',
        'pay_components',
        'payment_gateway_configs',
        'payroll_approval_settings',
        'payroll_item_lines',
        'payroll_items',
        'payroll_runs',
        'payslips',
        'petty_cash_registers',
        'petty_cash_vouchers',
        'po_match_results',
        'pos_cart_items',
        'pos_carts',
        'pos_pin_lockouts',
        'posting_rule_lines',
        'posting_rule_sets',
        'procurement_alerts',
        'procurement_document_sequences',
        'product_batches',
        'product_bundle_items',
        'product_serials',
        'product_variants',
        'purchase_order_items',
        'purchase_orders',
        'purchase_return_items',
        'purchase_returns',
        'recurring_expense_occurrences',
        'recurring_expense_schedules',
        'salary_structure_components',
        'salary_structures',
        'sale_invoice_sequences',
        'sale_invoices',
        'sale_items',
        'sale_payments',
        'sales',
        'statutory_schemes',
        'stock_movements',
        'stock_reservations',
        'stock_transfer_items',
        'stock_transfers',
        'store_credit_transactions',
        'store_credits',
        'supplier_addresses',
        'supplier_attachments',
        'supplier_contacts',
        'supplier_invoice_items',
        'supplier_invoices',
        'supplier_ledger_entries',
        'supplier_payments',
        'supplier_price_list_items',
        'system_settings',
        'tax_slabs',
        'tax_types',
        'toil_balances',
        'toil_claims',
        'toil_ledger_entries',
        'user_permission_overrides',
        'variant_branch_settings',
        'warehouse_zones',
        'warehouses',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }
            // Prefer dropColumn only — Laravel drops the index with the column on SQLite.
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
