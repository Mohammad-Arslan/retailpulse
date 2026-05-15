<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PermissionOverrideType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermissionOverride extends Model
{
    protected $fillable = [
        'user_id',
        'permission_id',
        'type',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => PermissionOverrideType::class,
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
