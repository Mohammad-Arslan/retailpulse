<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use Carbon\CarbonInterface;
use DomainException;

final class NoOpenFiscalYearException extends DomainException
{
    public function __construct(CarbonInterface $date)
    {
        parent::__construct(sprintf(
            'No open fiscal year covers %s. Create one in Accounting -> Fiscal Years.',
            $date->toDateString(),
        ));
    }
}
