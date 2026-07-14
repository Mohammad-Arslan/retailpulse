<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'driver',
    'name',
    'config_json',
    'status',
    'branch_id',
])]
final class AttendanceSource extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config_json' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'source_id');
    }
}
