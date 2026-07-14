<?php

declare(strict_types=1);

namespace App\Services\Hr\Contracts;

interface HrPayrollModuleGate
{
    /**
     * Whether $moduleKey is enabled for the given branch.
     * A null $branchId (head office) resolves to the union across branches.
     */
    public function isEnabled(string $moduleKey, ?int $branchId = null): bool;

    /**
     * Fully-resolved enabled module keys after walking config/hr_payroll_modules.php.
     *
     * @return list<string>
     */
    public function enabledModules(?int $branchId = null): array;
}
