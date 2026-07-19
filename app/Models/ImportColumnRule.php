<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'profile_id',
    'column_key',
    'mapped_to',
    'display_label',
    'rules',
    'is_required',
    'default_value',
    'transform',
    'sort_order',
])]
class ImportColumnRule extends Model
{
    protected $table = 'import_column_rules';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'transform' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ImportValidationProfile::class, 'profile_id');
    }
}
