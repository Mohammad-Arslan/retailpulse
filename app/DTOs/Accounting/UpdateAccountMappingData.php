<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\UpdateAccountMappingRequest;

final readonly class UpdateAccountMappingData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function fromRequest(UpdateAccountMappingRequest $request): self
    {
        return new self($request->validated());
    }
}
