<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'legal_entity_id',
    'branch_id',
    'status',
])]
class HolidayCalendar extends Model
{
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function dates(): HasMany
    {
        return $this->hasMany(HolidayDate::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(HolidayCalendarAssignment::class);
    }
}
