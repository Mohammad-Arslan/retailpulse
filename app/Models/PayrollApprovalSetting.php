<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'legal_entity_id',
    'requires_approval',
    'approval_limit',
    'use_workflow_engine',
])]
class PayrollApprovalSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'approval_limit' => 'decimal:4',
            'use_workflow_engine' => 'boolean',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }
}
