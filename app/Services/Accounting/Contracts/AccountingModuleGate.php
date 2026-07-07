<?php

declare(strict_types=1);

namespace App\Services\Accounting\Contracts;

interface AccountingModuleGate
{
    /**
     * Whether $moduleKey is enabled for the given branch. A null $branchId
     * (head office / no branch selected) always resolves to core-only.
     */
    public function isEnabled(string $moduleKey, ?int $branchId = null): bool;

    /**
     * Fully-resolved enabled module keys for the branch, after walking
     * the config/accounting_modules.php dependency chain.
     *
     * @return list<string>
     */
    public function enabledModules(?int $branchId = null): array;
}
