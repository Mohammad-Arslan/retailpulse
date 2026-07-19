<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'supplier_id',
    'label',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'country_code',
    'is_default',
    'created_by',
    'updated_by',
])]
class SupplierAddress extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
