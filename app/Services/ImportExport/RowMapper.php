<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

final class RowMapper
{
    /**
     * Map a spreadsheet row (file column headers) to system field keys.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $mapping  system_key => file_column_header
     * @return array<string, mixed>
     */
    public static function toSystemKeys(array $row, array $mapping): array
    {
        if ($mapping === []) {
            return $row;
        }

        $mapped = [];

        foreach ($mapping as $systemKey => $fileColumn) {
            if ($fileColumn === '') {
                continue;
            }

            if (array_key_exists($fileColumn, $row)) {
                $mapped[$systemKey] = $row[$fileColumn];
            }
        }

        return $mapped;
    }
}
