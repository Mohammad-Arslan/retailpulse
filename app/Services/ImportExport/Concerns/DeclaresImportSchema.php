<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Default schema declaration for single-table import handlers.
 * Override targetModels() and columnMap() when logical keys differ from physical columns
 * or when multiple tables are written.
 */
trait DeclaresImportSchema
{
    /**
     * @return list<class-string<Model>>
     */
    public function targetModels(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function columnMap(): array
    {
        $map = [];

        foreach ($this->columns() as $column) {
            $key = (string) ($column['key'] ?? '');
            if ($key !== '') {
                $map[$key] = $key;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public function compositeConstraintAdvisories(): array
    {
        return [];
    }

    public function isInsertOnly(): bool
    {
        return false;
    }
}
