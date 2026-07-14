<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StorePostingRuleSetRequest;

/**
 * Client-submitted fields for duplicating a posting rule set.
 * Identity fields (event_type, entity_type, currency_code) are copied from
 * the source rule set in PostingRuleService::duplicate() — never from the request.
 */
final readonly class StorePostingRuleSetData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<PostingRuleLineData>  $lines
     */
    public function __construct(
        public int $duplicateFromId,
        public array $attributes,
        public array $lines,
    ) {}

    public static function fromRequest(StorePostingRuleSetRequest $request): self
    {
        $validated = $request->validated();
        $duplicateFromId = (int) $validated['duplicate_from_id'];
        $lines = array_map(
            fn (array $line) => PostingRuleLineData::fromArray($line),
            $validated['lines'],
        );

        unset($validated['lines'], $validated['duplicate_from_id']);

        return new self($duplicateFromId, $validated, $lines);
    }
}
