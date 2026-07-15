<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\ImportExport\ImportExportRegistry;

final class ImportExportAuthorization
{
    /**
     * @return array{import: string, export: string}
     */
    public static function permissionsFor(string $entityType): array
    {
        return match ($entityType) {
            'inventory' => [
                'import' => 'inventory.import-opening-stock',
                'export' => 'inventory.reports',
            ],
            'inventory-adjustments' => [
                'import' => 'inventory.bulk-adjustment-import',
                'export' => 'inventory.reports',
            ],
            'categories', 'brands', 'units', 'products' => [
                'import' => 'products.import',
                'export' => 'products.export',
            ],
            'customers' => [
                'import' => 'customers.import',
                'export' => 'customers.export',
            ],
            'employees' => [
                'import' => 'employees.import',
                'export' => 'employees.export',
            ],
            'suppliers' => [
                'import' => 'suppliers.import',
                'export' => 'suppliers.export',
            ],
            'supplier-price-lists' => [
                'import' => 'supplier-price-lists.import',
                'export' => 'supplier-price-lists.export',
            ],
            'coa' => [
                'import' => 'accounting.import-coa',
                'export' => 'accounting.export-reports',
            ],
            'opening-balances' => [
                'import' => 'accounting.import-opening-balances',
                'export' => 'accounting.export-reports',
            ],
            default => [
                'import' => 'products.import',
                'export' => 'products.export',
            ],
        };
    }

    public static function canImport(?User $user, string $entityType): bool
    {
        if ($user === null) {
            return false;
        }

        if (! in_array($entityType, ImportExportRegistry::allEntities(), true)) {
            return false;
        }

        $permissions = self::permissionsFor($entityType);

        return $user->can($permissions['import']);
    }

    public static function canExport(?User $user, string $entityType): bool
    {
        if ($user === null) {
            return false;
        }

        if (! in_array($entityType, ImportExportRegistry::allEntities(), true)) {
            return false;
        }

        $permissions = self::permissionsFor($entityType);

        return $user->can($permissions['export']);
    }
}
