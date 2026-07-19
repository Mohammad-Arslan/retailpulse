# Tenant Schema Preparation

Status: Applied (schema prep only — no enforcement)
Date: 2026-07-19
Related: ADR-001, ADR-015, Phase 28 SaaS Multi-Tenancy

## Purpose

RetailPulse is designed as a SaaS-first ERP. Target architecture: Shared Database, Shared Schema, Row-Level Isolation via `tenant_id`.

This work ONLY prepares the schema so Phase 28 can implement tenancy with minimal breaking migrations. The application remains single-tenant until Phase 28.

Do NOT treat `tenant_id` as enforced isolation. There is no `TenantScope` / `TenantContext` yet.

## Already had tenant_id (17)

- branches
- brands
- categories
- customer_loyalty_transactions
- customer_loyalty_wallets
- financial_settings
- identifier_sequences
- import_export_jobs
- import_validation_profiles
- loyalty_programs
- organization_entities
- products
- supplier_performance_scores
- supplier_price_lists
- suppliers
- units
- users

## Added by migration 2026_07_19_140000… (153)

- account_mappings
- accounting_events
- approval_delegations
- ar_aging_snapshots
- asset_categories
- attendance_records
- attendance_sources
- audit_logs
- bank_accounts
- bank_reconciliation_matches
- bank_statement_lines
- bin_locations
- branch_accounting_profiles
- branch_hr_profiles
- branch_product_prices
- chart_of_accounts
- cheques
- coa_import_batches
- coa_import_lines
- cost_centre_allocations
- cost_centres
- count_schedule_rules
- count_session_lines
- count_sessions
- credit_notes
- customer_ar_ledger
- customer_groups
- customer_loyalty_events
- customer_reminder_logs
- customer_wallet_transactions
- customer_wallets
- customer_write_offs
- customers
- debit_notes
- departments
- designations
- document_sequences
- employee_assignment_history
- employee_attachments
- employee_bank_accounts
- employee_branch_assignments
- employee_dependents
- employee_manager_history
- employee_medical_profiles
- employee_profiles
- employee_shift_preferences
- employees
- expense_approval_policies
- expense_attachments
- expense_categories
- expenses
- fbr_invoice_queue
- fbr_invoice_sequences
- fiscal_year_reopen_requests
- fiscal_years
- fixed_assets
- goods_receiving_notes
- grades
- grn_items
- holiday_calendars
- holiday_dates
- hr_employment_types
- hr_entity_settings
- images
- import_column_rules
- import_row_errors
- intercompany_transactions
- inventories
- inventory_cost_layers
- journal_entries
- journal_transactions
- landed_cost_allocations
- landed_cost_entries
- leave_encashments
- leave_entitlements
- leave_policies
- leave_request_reschedules
- leave_requests
- leave_types
- leave_year_end_lines
- leave_year_end_runs
- loyalty_approval_policies
- loyalty_campaigns
- loyalty_expiry_rules
- loyalty_points
- loyalty_program_tiers
- loyalty_rules
- loyalty_tiers
- opening_balance_import_batches
- opening_balance_import_lines
- opening_balance_reconciliations
- overtime_multipliers
- overtime_policies
- overtime_records
- pay_components
- payment_gateway_configs
- payroll_approval_settings
- payroll_item_lines
- payroll_items
- payroll_runs
- payslips
- petty_cash_registers
- petty_cash_vouchers
- po_match_results
- pos_cart_items
- pos_carts
- pos_pin_lockouts
- posting_rule_lines
- posting_rule_sets
- procurement_alerts
- procurement_document_sequences
- product_batches
- product_bundle_items
- product_serials
- product_variants
- purchase_order_items
- purchase_orders
- purchase_return_items
- purchase_returns
- recurring_expense_occurrences
- recurring_expense_schedules
- salary_structure_components
- salary_structures
- sale_invoice_sequences
- sale_invoices
- sale_items
- sale_payments
- sales
- statutory_schemes
- stock_movements
- stock_reservations
- stock_transfer_items
- stock_transfers
- store_credit_transactions
- store_credits
- supplier_addresses
- supplier_attachments
- supplier_contacts
- supplier_invoice_items
- supplier_invoices
- supplier_ledger_entries
- supplier_payments
- supplier_price_list_items
- system_settings
- tax_slabs
- tax_types
- toil_balances
- toil_claims
- toil_ledger_entries
- user_permission_overrides
- variant_branch_settings
- warehouse_zones
- warehouses

## Excluded — Platform (Cat 3)

- cache — Laravel cache store (framework infrastructure)
- cache_locks — Laravel cache lock table
- failed_jobs — queue failure tracking
- job_batches — queue batch metadata
- jobs — queue jobs table
- migrations — schema version tracking
- model_has_permissions — Spatie permission pivot (also listed under pivots)
- model_has_roles — Spatie role pivot (also listed under pivots)
- password_reset_tokens — auth password-reset tokens
- permissions — Spatie global permission catalog
- personal_access_tokens — API token store (Sanctum)
- role_has_permissions — Spatie role↔permission pivot (also listed under pivots)
- roles — Spatie global role catalog
- sessions — HTTP session store

## Excluded — Global reference (Cat 4)

- currencies — shared FX/currency reference for all tenants
- exchange_rates — shared FX rates; not tenant-owned business data

## Excluded — Pivots

- branch_user — membership pivot; tenancy follows parent `users` / `branches`
- loyalty_program_branches — assignment pivot; tenancy follows parent programs/branches
- holiday_calendar_assignments — assignment pivot; tenancy follows parent calendars
- model_has_permissions, model_has_roles, role_has_permissions — Spatie pivots; stay with global roles/permissions catalog until Phase 28 decides on teams / tenant-scoped roles

## Special notes

- units already has `tenant_id` (tenant catalog in this codebase; ADR Cat 4 lists UoM as global — observation for Phase 28)
- No organizations table; Cat 1 roots are users, branches, organization_entities
- roles/permissions remain global platform catalog for now (Spatie teams / tenant-scoped roles = Phase 28 decision)
- system_settings: nullable `tenant_id` added; null may mean platform-global vs tenant override (Phase 28 must define write path)
- import_validation_profiles / import_export_jobs already had non-nullable `tenant_id` — unchanged
- No FK to tenants table yet (tenants created in Phase 28)

## Phase boundary

Phase 28 implements: Tenant resolver, `TenantContext`, `TenantScope`, middleware, security, tenant-aware cache/queues/storage/search, provisioning, billing, cross-tenant protection.

Phase 29 remains Workflow Engine (do not conflate).

## Migration

`database/migrations/2026_07_19_140000_add_nullable_tenant_id_for_saas_schema_prep.php`

Models: `tenant_id` added to Fillable on corresponding Eloquent models (170 models total with `tenant_id` in Fillable: 17 prior + 153 this migration). No business logic changes.
