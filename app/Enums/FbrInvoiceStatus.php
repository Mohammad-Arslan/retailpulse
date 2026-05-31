<?php

declare(strict_types=1);

namespace App\Enums;

enum FbrInvoiceStatus: string
{
    case NotApplicable = 'not_applicable';
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Failed = 'failed';
    case Blocked = 'blocked';
}
