<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ChartOfAccount;

final class ChartOfAccountPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(ChartOfAccount $account, int $depth = 0): array
    {
        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type->value,
            'parent_id' => $account->parent_id,
            'account_level' => $account->account_level,
            'is_group' => $account->is_group,
            'is_postable' => $account->is_postable,
            'branch_id' => $account->branch_id,
            'branch' => $account->branch ? [
                'id' => $account->branch->id,
                'name' => $account->branch->name,
            ] : null,
            'legal_entity_id' => $account->legal_entity_id,
            'currency_code' => $account->currency_code,
            'status' => $account->status,
            'effective_from' => $account->effective_from?->toDateString(),
            'effective_to' => $account->effective_to?->toDateString(),
            'depth' => $depth,
        ];
    }
}
