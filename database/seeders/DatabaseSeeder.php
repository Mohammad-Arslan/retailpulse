<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            BranchSeeder::class,
            UnitSeeder::class,
            SupplierSeeder::class,
            IdentifierSequenceSeeder::class,
            OrganizationEntitySeeder::class,
            ChartOfAccountsSeeder::class,
            TaxTypeSeeder::class,
            AccountMappingsSeeder::class,
            PostingRulesSeeder::class,
            FiscalYearSeeder::class,
            FinancialSettingsSeeder::class,
            FileStorageSettingsSeeder::class,
            CurrenciesSeeder::class,
            GradeSeeder::class,
            HrEmploymentTypeSeeder::class,
            DesignationSeeder::class,
            DepartmentSeeder::class,
            EmployeeSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
