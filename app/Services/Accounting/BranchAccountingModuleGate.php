<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\BranchAccountingProfile;
use App\Services\Accounting\Contracts\AccountingModuleGate;

final class BranchAccountingModuleGate implements AccountingModuleGate
{
    /** @var array<int|string, list<string>> */
    private array $resolvedCache = [];

    public function isEnabled(string $moduleKey, ?int $branchId = null): bool
    {
        return in_array($moduleKey, $this->enabledModules($branchId), true);
    }

    /**
     * @return list<string>
     */
    public function enabledModules(?int $branchId = null): array
    {
        $cacheKey = $branchId ?? 'head_office';

        return $this->resolvedCache[$cacheKey] ??= $this->computeEnabledModules($branchId);
    }

    /**
     * @return list<string>
     */
    private function computeEnabledModules(?int $branchId): array
    {
        $definitions = config('accounting_modules', []);
        $stored = $branchId === null ? [] : $this->storedModules($branchId);

        $enabled = [];

        foreach (array_keys($definitions) as $moduleKey) {
            if ($this->resolves($moduleKey, $stored, $definitions, [])) {
                $enabled[] = $moduleKey;
            }
        }

        return $enabled;
    }

    /**
     * Recursive dependency-chain walk. $visiting guards against a config cycle
     * (the shipped config has none, but this keeps a future edit from recursing forever).
     *
     * @param  list<string>  $stored
     * @param  array<string, array{always_enabled?: bool, requires?: list<string>}>  $definitions
     * @param  list<string>  $visiting
     */
    private function resolves(string $moduleKey, array $stored, array $definitions, array $visiting): bool
    {
        if (($definitions[$moduleKey]['always_enabled'] ?? false) === true) {
            return true;
        }

        if (in_array($moduleKey, $visiting, true)) {
            return false;
        }

        if (! in_array($moduleKey, $stored, true)) {
            return false;
        }

        foreach ($definitions[$moduleKey]['requires'] ?? [] as $dependency) {
            if (! $this->resolves($dependency, $stored, $definitions, [...$visiting, $moduleKey])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function storedModules(int $branchId): array
    {
        $profile = BranchAccountingProfile::query()->where('branch_id', $branchId)->first();
        $stored = $profile?->accounting_enabled_modules;

        return is_array($stored) && $stored !== [] ? $stored : ['core'];
    }
}
