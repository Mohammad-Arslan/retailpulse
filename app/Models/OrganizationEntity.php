<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'legal_name',
    'tax_registration_no',
    'functional_currency_code',
    'status',
])]
class OrganizationEntity extends Model
{
    protected function casts(): array
    {
        return [];
    }
}
