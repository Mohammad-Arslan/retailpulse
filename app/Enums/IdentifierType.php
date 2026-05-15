<?php

declare(strict_types=1);

namespace App\Enums;

enum IdentifierType: string
{
    case Sku = 'sku';
    case Barcode = 'barcode';
}
