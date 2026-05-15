<?php

declare(strict_types=1);

namespace App\Enums;

enum PermissionOverrideType: string
{
    case Grant = 'grant';
    case Revoke = 'revoke';
}
