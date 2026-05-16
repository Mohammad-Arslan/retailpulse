<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

use App\Http\Requests\Admin\UpdateSettingsGroupRequest;

final readonly class UpdateSettingsGroupData
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public string $group,
        public array $values,
    ) {}

    public static function fromRequest(UpdateSettingsGroupRequest $request, string $group): self
    {
        return new self(
            group: $group,
            values: $request->validated('values'),
        );
    }
}
