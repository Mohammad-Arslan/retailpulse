<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccountMapping;

final class AccountMappingPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(AccountMapping $mapping): array
    {
        return [
            'id' => $mapping->id,
            'mapping_key' => $mapping->mapping_key,
            'account_id' => $mapping->account_id,
            'account' => $mapping->account ? [
                'id' => $mapping->account->id,
                'code' => $mapping->account->code,
                'name' => $mapping->account->name,
            ] : null,
            'branch_id' => $mapping->branch_id,
            'branch' => $mapping->branch ? [
                'id' => $mapping->branch->id,
                'name' => $mapping->branch->name,
            ] : null,
            'warehouse_id' => $mapping->warehouse_id,
            'product_category_id' => $mapping->product_category_id,
            'payment_method' => $mapping->payment_method,
            'currency_code' => $mapping->currency_code,
            'legal_entity_id' => $mapping->legal_entity_id,
            'effective_from' => $mapping->effective_from?->toDateString(),
            'effective_to' => $mapping->effective_to?->toDateString(),
            'status' => $mapping->status,
            'priority' => $mapping->priority,
        ];
    }
}
