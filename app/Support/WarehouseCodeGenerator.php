<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\Contracts\WarehouseRepositoryInterface;
use Illuminate\Support\Str;

final class WarehouseCodeGenerator
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
    ) {}

    public function generate(int $branchId, string $name): string
    {
        $base = self::baseFromName($name);
        $code = $base;
        $suffix = 2;

        while ($this->warehouses->codeExistsForBranch($branchId, $code)) {
            $code = self::appendSuffix($base, $suffix++);
        }

        return $code;
    }

    public static function previewFromName(string $name): string
    {
        return self::baseFromName($name);
    }

    private static function baseFromName(string $name): string
    {
        $slug = strtoupper(Str::slug(trim($name), '-'));
        $base = preg_replace('/[^A-Z0-9-]/', '', $slug) ?? '';

        if ($base === '' || strlen($base) < 2) {
            $base = 'WH';
        }

        return Str::limit($base, 28, '');
    }

    private static function appendSuffix(string $base, int $suffix): string
    {
        $suffixStr = (string) $suffix;
        $maxBaseLength = 32 - strlen($suffixStr) - 1;

        return Str::limit($base, max(1, $maxBaseLength), '').'-'.$suffixStr;
    }
}
