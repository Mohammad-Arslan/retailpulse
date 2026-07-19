<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IntercompanySettlementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'transfer_reference_type',
    'transfer_reference_id',
    'source_legal_entity_id',
    'destination_legal_entity_id',
    'source_journal_entry_id',
    'destination_journal_entry_id',
    'settlement_status',
    'settled_at',
])]
class IntercompanyTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'settlement_status' => IntercompanySettlementStatus::class,
            'settled_at' => 'datetime',
        ];
    }

    public function sourceLegalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'source_legal_entity_id');
    }

    public function destinationLegalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'destination_legal_entity_id');
    }
}
