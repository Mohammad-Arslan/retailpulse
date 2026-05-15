<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BarcodeFormat;
use App\Enums\IdentifierType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'key',
    'identifier_type',
    'format',
    'prefix',
    'suffix',
    'pad_length',
    'last_value',
    'is_active',
])]
class IdentifierSequence extends Model
{
    protected function casts(): array
    {
        return [
            'identifier_type' => IdentifierType::class,
            'format' => BarcodeFormat::class,
            'pad_length' => 'integer',
            'last_value' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
