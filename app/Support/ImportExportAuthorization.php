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
                'import' => 'inventory.adjust',
                'export' => 'inventory.view',
            ],
            'categories', 'brands', 'units', 'products' => [
                'import' => 'products.import',
                'export' => 'products.export',
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
