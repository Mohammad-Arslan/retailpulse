<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\BranchHrProfile;
use App\Services\Hr\Contracts\HrPayrollModuleGate;

final class BranchHrPayrollModuleGate implements HrPayrollModuleGate
{
    /** @var list<string> */
    private const DEFAULT_MODULES = ['expenses', 'hr'];

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
        $definitions = config('hr_payroll_modules', []);
        $stored = $branchId === null ? $this->allStoredModules() : $this->storedModules($branchId);

        $enabled = [];

        foreach (array_keys($definitions) as $moduleKey) {
            if ($this->resolves($moduleKey, $stored, $definitions, [])) {
                $enabled[] = $moduleKey;
            }
        }

        return $enabled;
    }

    /**
     * @param  list<string>  $stored
     * @param  array<string, array{requires?: list<string>}>  $definitions
     * @param  list<string>  $visiting
     */
    private function resolves(string $moduleKey, array $stored, array $definitions, array $visiting): bool
    {
        if (! array_key_exists($moduleKey, $definitions)) {
            return false;
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
        $profile = BranchHrProfile::query()->where('branch_id', $branchId)->first();
        $stored = $profile?->hr_enabled_modules;

        return is_array($stored) && $stored !== [] ? $stored : self::DEFAULT_MODULES;
    }

    /**
     * @return list<string>
     */
    private function allStoredModules(): array
    {
        $modules = BranchHrProfile::query()
            ->pluck('hr_enabled_modules')
            ->flatMap(fn ($value) => is_array($value) ? $value : [])
            ->unique()
            ->values()
            ->all();

        if ($modules === []) {
            return self::DEFAULT_MODULES;
        }

        return $modules;
    }
}
