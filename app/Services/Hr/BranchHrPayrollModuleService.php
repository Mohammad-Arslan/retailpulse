<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\BranchHrProfile;
use Illuminate\Validation\ValidationException;

final class BranchHrPayrollModuleService
{
    /**
     * @return list<array{key: string, always_enabled: bool, requires: list<string>}>
     */
    public function moduleCatalog(): array
    {
        $definitions = config('hr_payroll_modules', []);
        $catalog = [];

        foreach ($definitions as $key => $definition) {
            $catalog[] = [
                'key' => (string) $key,
                'always_enabled' => in_array($key, ['expenses', 'hr'], true),
                'requires' => array_values($definition['requires'] ?? []),
            ];
        }

        return $catalog;
    }

    /**
     * @return list<string>
     */
    public function storedModules(int $branchId): array
    {
        $profile = BranchHrProfile::query()->where('branch_id', $branchId)->first();
        $stored = $profile?->hr_enabled_modules;

        return is_array($stored) && $stored !== []
            ? $this->normalizeModuleList($stored)
            : ['expenses', 'hr', 'holiday_calendar'];
    }

    /**
     * @param  list<string>  $requested
     */
    public function updateModules(int $branchId, array $requested): BranchHrProfile
    {
        $normalized = $this->normalizeAndExpand($requested);

        return BranchHrProfile::query()->updateOrCreate(
            ['branch_id' => $branchId],
            [
                'hr_enabled_modules' => $normalized,
            ],
        );
    }

    /**
     * @param  list<string>  $requested
     * @return list<string>
     */
    public function normalizeAndExpand(array $requested): array
    {
        $definitions = config('hr_payroll_modules', []);
        $knownKeys = array_keys($definitions);
        $unknown = array_values(array_diff($requested, $knownKeys));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'modules' => __('Unknown HR module(s): :modules.', [
                    'modules' => implode(', ', $unknown),
                ]),
            ]);
        }

        $expanded = [];

        foreach (array_values(array_unique([...$requested, 'expenses', 'hr'])) as $moduleKey) {
            $this->collectWithRequires($moduleKey, $definitions, $expanded);
        }

        return $this->normalizeModuleList($expanded);
    }

    /**
     * @param  array<string, array{requires?: list<string>}>  $definitions
     * @param  list<string>  $collector
     */
    private function collectWithRequires(string $moduleKey, array $definitions, array &$collector): void
    {
        if (! isset($definitions[$moduleKey]) || in_array($moduleKey, $collector, true)) {
            return;
        }

        foreach ($definitions[$moduleKey]['requires'] ?? [] as $dependency) {
            $this->collectWithRequires($dependency, $definitions, $collector);
        }

        $collector[] = $moduleKey;
    }

    /**
     * @param  list<string>  $modules
     * @return list<string>
     */
    private function normalizeModuleList(array $modules): array
    {
        $knownKeys = array_keys(config('hr_payroll_modules', []));
        $filtered = array_values(array_unique(array_intersect($modules, $knownKeys)));

        foreach (['expenses', 'hr'] as $required) {
            if (! in_array($required, $filtered, true)) {
                array_unshift($filtered, $required);
            }
        }

        sort($filtered);

        return array_values(array_unique($filtered));
    }
}
