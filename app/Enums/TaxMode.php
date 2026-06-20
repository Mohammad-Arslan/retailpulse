<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxMode: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';
}
