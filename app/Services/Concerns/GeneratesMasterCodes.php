<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Services\Accounting\DocumentNumberService;

trait GeneratesMasterCodes
{
    abstract protected function documentNumbers(): DocumentNumberService;

    protected function peekMasterCode(string $documentType, string $prefix): string
    {
        return $this->documentNumbers()->peek($documentType, $prefix);
    }

    protected function nextMasterCode(string $documentType, string $prefix): string
    {
        return $this->documentNumbers()->next($documentType, $prefix);
    }
}
