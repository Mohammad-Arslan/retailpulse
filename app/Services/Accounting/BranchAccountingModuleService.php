<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\BranchAccountingProfile;
use Illuminate\Validation\ValidationException;

final class BranchAccountingModuleService
{
    /**
     * @return list<array{key: string, always_enabled: bool, requires: list<string>}>
     */
    public function moduleCatalog(): array
    {
        $definitions = config('accounting_modules', []);
        $catalog = [];

        foreach ($definitions as $key => $definition) {
            $catalog[] = [
                'key' => (string) $key,
                'always_enabled' => (bool) ($definition['always_enabled'] ?? false),
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
        $profile = BranchAccountingProfile::query()->where('branch_id', $branchId)->first();
        $stored = $profile?->accounting_enabled_modules;

        return is_array($stored) && $stored !== []
            ? $this->normalizeModuleList($stored)
            : ['core'];
    }

    /**
     * @param  list<string>  $requested
     */
    public function updateModules(int $branchId, array $requested): BranchAccountingProfile
    {
        $normalized = $this->normalizeAndExpand($requested);

        return BranchAccountingProfile::query()->updateOrCreate(
            ['branch_id' => $branchId],
            [
                'status' => 'active',
                'accounting_enabled_modules' => $normalized,
            ],
        );
    }

    /**
     * Keep core, reject unknown keys, and expand dependency closure so enabling
     * credit_notes also stores ar_ap. Disabling a dependency while leaving its
     * dependents checked will re-expand that dependency — the UI must cascade-uncheck.
     *
     * @param  list<string>  $requested
     * @return list<string>
     */
    public function normalizeAndExpand(array $requested): array
    {
        $definitions = config('accounting_modules', []);
        $knownKeys = array_keys($definitions);
        $unknown = array_values(array_diff($requested, $knownKeys));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'modules' => __('Unknown accounting module(s): :modules.', [
                    'modules' => implode(', ', $unknown),
                ]),
            ]);
        }

        $expanded = [];

        foreach (array_values(array_unique([...$requested, 'core'])) as $moduleKey) {
            $this->collectWithRequires($moduleKey, $definitions, $expanded);
        }

        return $this->normalizeModuleList($expanded);
    }

    /**
     * @param  array<string, array{always_enabled?: bool, requires?: list<string>}>  $definitions
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
        $knownKeys = array_keys(config('accounting_modules', []));
        $filtered = array_values(array_unique(array_intersect($modules, $knownKeys)));

        if (! in_array('core', $filtered, true)) {
            array_unshift($filtered, 'core');
        }

        sort($filtered);

        return $filtered;
    }
}
