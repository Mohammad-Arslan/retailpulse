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
    'name',
    'email',
    'phone',
    'role',
    'is_primary',
    'created_by',
    'updated_by',
])]
class SupplierContact extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
