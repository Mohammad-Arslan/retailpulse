<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\UpdatePostingRuleSetRequest;

final readonly class UpdatePostingRuleSetData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<PostingRuleLineData>  $lines
     */
    public function __construct(
        public array $attributes,
        public array $lines,
    ) {}

    public static function fromRequest(UpdatePostingRuleSetRequest $request): self
    {
        $validated = $request->validated();
        $lines = array_map(
            fn (array $line) => PostingRuleLineData::fromArray($line),
            $validated['lines'],
        );
        unset($validated['lines']);

        return new self($validated, $lines);
    }
}
