<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PostingRuleLine;
use App\Models\PostingRuleSet;

final class PostingRuleSetPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(PostingRuleSet $set): array
    {
        return [
            'id' => $set->id,
            'code' => $set->code,
            'name' => $set->name,
            'event_type' => $set->event_type,
            'entity_type' => $set->entity_type,
            'branch_id' => $set->branch_id,
            'priority' => $set->priority,
            'effective_from' => $set->effective_from?->toDateString(),
            'effective_to' => $set->effective_to?->toDateString(),
            'status' => $set->status,
            'lines_count' => $set->lines_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forEdit(PostingRuleSet $set): array
    {
        return [
            'id' => $set->id,
            'code' => $set->code,
            'name' => $set->name,
            'event_type' => $set->event_type,
            'entity_type' => $set->entity_type,
            'branch_id' => $set->branch_id,
            'legal_entity_id' => $set->legal_entity_id,
            'currency_code' => $set->currency_code,
            'priority' => $set->priority,
            'effective_from' => $set->effective_from?->toDateString(),
            'effective_to' => $set->effective_to?->toDateString(),
            'status' => $set->status,
            'lines' => $set->lines->map(fn (PostingRuleLine $line) => [
                'id' => $line->id,
                'sequence' => $line->sequence,
                'entry_side' => $line->entry_side->value,
                'account_resolution_type' => $line->account_resolution_type->value,
                'account_id' => $line->account_id,
                'account' => $line->account ? [
                    'id' => $line->account->id,
                    'code' => $line->account->code,
                    'name' => $line->account->name,
                ] : null,
                'account_mapping_key' => $line->account_mapping_key,
                'amount_source' => $line->amount_source->value,
                'narration_template' => $line->narration_template,
                'required' => $line->required,
                'status' => $line->status,
            ])->values()->all(),
        ];
    }
}
