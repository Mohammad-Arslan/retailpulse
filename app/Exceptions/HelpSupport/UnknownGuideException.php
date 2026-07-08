<?php

declare(strict_types=1);

namespace App\Exceptions\HelpSupport;

use InvalidArgumentException;

final class UnknownGuideException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('This guide does not exist.');
    }
}
